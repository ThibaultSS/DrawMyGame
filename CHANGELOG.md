# DrawMyGame — August 2026

Everything in this repository from `0987a16` (4 August) onwards was rebuilt or reworked. The June commits are the original version; this file describes what the application looks like **after** that work, and what changed to get there.

Part 1 explains how it works now. Part 2 lists the changes, grouped by what they were for. Part 3 records the things that were deliberately left alone, and why.

> The last section of Part 2 is still in the working tree, not yet committed.

---

# Part 1 — How it works now

## The idea

You draw a platformer level on paper, photograph it, and play it. The picture is read pixel by pixel: areas of one colour become platforms, the goal, the player and hazards, and those shapes are turned into real physics bodies.

Nothing about the level is stored in a database. The picture *is* the level.

## The one thing to understand first: where a level lives

**A level you are playing lives in your browser, not on the server.**

Roughly nine out of ten levels are played once and never kept. Storing every one of them meant writing files the server would only have to delete again later. So the picture is held client-side — in IndexedDB, via `resources/js/levelStore.js` — from the moment you pick it, and is uploaded only if you press **Save Drawing**.

Consequences worth knowing:

- `POST /save-drawing` is the **only** route that accepts a file.
- A level that has not been saved has no URL. It cannot be linked to, and it is not on the disk.
- A level that *has* been saved is served from the private disk through one authorised route.

## The two ways in

| | Photograph a drawing | Draw it in the browser |
| --- | --- | --- |
| Page | `/upload` | `/draw` |
| Colours | you pick them with an eyedropper | fixed palette, no picking |
| Next step | `/game-setting` | straight to `/game` |

The drawn palette is a constant in `DrawnLevelController`: platform `#000000`, goal `#00aa00`, player `#0000ff`, hazard `#ff0000`. They sit far apart in RGB on purpose — the detector matches colours with a tolerance of 70, so near neighbours would bleed into one another.

## The request flow

**Photographing a drawing**

1. `GET /upload` — you choose a file. The page checks it locally (not an SVG, ≤ 10 MB, and the browser can actually decode it), stores it with `putLevel(file)` and moves on. **No request carries the image.**
2. `GET /game-setting` — the eyedropper. The page reads the picture out of the level store and sends you back to `/upload` if there is nothing there. You arm a role, then click that colour in the photo.
3. `POST /start-game` — the four `#rrggbb` values are validated and written to the session. Speed, jump height and "which drawing am I replaying" are cleared, because this is a new game.
4. `GET /game` — redirects to `/upload` unless the session holds all four colours. Otherwise the page resolves the picture from the store, copies the inputs onto `window.*` and boots Phaser.

**Drawing in the browser**

`GET /draw` → you draw → the page checks that the engine would actually *find* a platform, a goal and a player (it runs the real detector over the canvas, so a mark too small to be detected is reported as too small, not as missing) → `putLevel(blob)` → posts the fixed palette to the same `POST /start-game` → `/game`.

**Saving**

`POST /save-drawing` is where the server first keeps anything. It receives either:

- `levelImage` — the level the browser was holding, uploaded now, or
- `drawingId` — a level the server already has: your own being re-tuned, or someone else's being copied.

The four colours come from the session and the speed/jump from the sliders' current positions, so the whole game is saved, not just the picture. Afterwards the session records the new drawing, so pressing Save a second time **updates** it instead of creating a duplicate.

**Replaying** — `GET /play/{drawing}`

Published drawings are playable by everyone, unpublished ones only by their owner; anything else is a 404. A drawing saved with its settings fills in colours, speed and jump and goes straight to `/game`, playing exactly as its author tuned it. A drawing saved before settings existed still detours through `/game-setting` to re-pick colours.

## What the server keeps

**In the session:** the four colours, `gameSpeed`, `jumpHeight`, and `replayDrawingId` (which saved drawing, if any, is being played). Never the picture.

**In the database:** `saved_drawings` — the image path, the owner, `published`, plus the whole game: four colour columns and `speed`/`jump_height`. All nullable, because drawings from before that migration have none; `hasGameSettings()` is what tells the two apart. Rows are soft-deleted.

**On disk:** `storage/app/private/levels`, the **private** disk. There is no public URL and `storage:link` is not part of this flow. Images are served only by `LevelImageController` at `/drawings/{drawing}/image`, which checks published-or-owner on every request.

## Security model

- Anything you may not see is a **404, never a 403** — an id that belongs to someone else is indistinguishable from one that does not exist.
- Login failures never say whether it was the email or the password that was wrong, and are rate-limited per email *and* IP together, so someone guessing at your account cannot lock you out from elsewhere.
- Uploads are validated on content, not on the file name: `File::image()->max(10 * 1024)` rejects a PDF called `level.png`, and excludes SVG, which can carry scripts.
- Throttles use **named buckets** (`throttle:20,1,uploads`, `throttle:5,1,registration`). An unnamed throttle shares a single counter per visitor across the whole site, so hitting one limit would have locked unrelated forms.
- Registering and logging in both regenerate the session id, so a session planted beforehand cannot be reused.
- Shared Inertia props carry only `id`, `username` and `initials` — everything shared is readable in the page source.

## The game engine

`resources/js/game/` — `main.js` (the Phaser scene) plus three modules it leans on: `config.js` (world size and detection constants), `colorDetection.js` and `shapeTracing.js`. The last two are pure functions and are unit-tested.

The pipeline, in `imageToLevelData()`:

1. `detectShapes` finds the connected regions for all four colours in **one pass** over the photo, tracking visited pixels as one bit per colour. The knobs are `colorTolerance` (70) and `minShapeSize` (300 px); `maxShapeSize` bails out of a fill that is swallowing the page.
2. `traceBoundary` walks the outer edge with Moore-neighbour tracing, producing a ring that never crosses itself.
3. `simplifyOutline` runs Douglas-Peucker, raising epsilon until the ring is under 64 points — then *lowering* it again if the shape has collapsed into a line, which is what happens to a thin diagonal bar.
4. `fitToWorld` scales both axes by the **same** factor and centres the result, so a 4:3 photo is not squashed into the 1500×800 world. The physics bounds are set to that fitted rectangle, so you cannot walk into the empty bars beside the drawing.

Each shape gets a real polygon collider (`matter.add.fromVertices`), falling back to a bounding box only if decomposition fails — so collisions follow the drawn outline. Goal and hazard bodies are labelled, and the label is written to **every part** of the body, because a concave shape is split into a compound body and Matter reports collisions on the parts.

The player is the **largest** shape in the player colour, so a stray mark cannot become a second player.

**Two contracts between the page and the engine:** the `window.*` inputs (`levelImage`, the four colours, `gamePaused`), and the element ids the engine looks up (`game-container`, `loading-screen`, `popup`, `popup-message`, `speedSlider`, `jumpSlider`). Renaming either means changing both sides. The engine does not run on import — `main.js` exports `bootGame()`, which resets its module state and returns the `Phaser.Game`; the page boots it on mount and destroys it on unmount.

## Front end

Vue 3 pages in `resources/js/Pages/`, all inside `Layouts/AppLayout.vue`. The only Blade file left is `resources/views/app.blade.php`, the Inertia root. Shared pieces live in `resources/js/Components/`: `Pagination.vue` and `FlashToast.vue` (which turns any `back()->with('message', …)` into a toast).

Styling is Tailwind v4 with the house-style tokens in `resources/css/site.css` — `bg-page`, `text-ink`, `border-sub`, `text-error`. White page, black ink, `#D9D9D9` for lines. There is no custom stylesheet and no component `<style>` block except the game canvas scaling rule.

Vite has two entry points: `site.css` and `app.js`. Phaser has none — the game page imports the engine lazily, so it is split into a chunk (~1.4 MB) that only that page downloads.

---

# Part 2 — What changed in August

## 1. The engine was split up *(4 Aug, `e82a0c7`)*

The engine was a single 824-line `resources/js/game/main.js`. It came out at 444 lines, with the pixel work moved into `config.js`, `colorDetection.js` and `shapeTracing.js` — and vitest added, so those three are unit-tested.

The rewrite also fixed real behaviour, not just layout: colour detection went to a single pass over the image, outline tracing moved to Moore-neighbour tracing, aspect ratio is now preserved instead of the photo being stretched to fill the canvas, and **shapes collide on their real outline instead of on their bounding box**.

## 2. Blade → Inertia and Vue *(4–5 Aug)*

Every page became a Vue component served through Inertia. The old look — a banner video, animated PNG borders swapped on a `setInterval`, frames around every image — was not carried over; the rebuilt pages are typographic and use the house style.

The visible wins: forms keep what you typed when validation fails, navigation no longer reloads the page, and publish/delete update just the card that changed instead of reloading the gallery and losing your scroll position.

## 3. Review fixes *(5 Aug)*

Each of these was a point raised in review:

| Raised | Now |
| --- | --- |
| Sloppy code, unused code, inconsistent indentation | Pint enforced across the codebase |
| Random inline styles | Two remain, both showing a colour that is by definition not in the palette (a swatch of what you picked) |
| Inline scripts | None; all behaviour is in components |
| No components | `AppLayout`, `Pagination`, `FlashToast` |
| `onclick` on buttons and a `<div onclick>` in the layout | Real `<button type="button">` handlers; the community card is a real `<Link>`, so middle-click and "open in new tab" work |
| No named routes | Every route named |
| Inline code in routes | No closures; static pages use `Route::inertia`, everything else a controller |
| No validation classes | Form Requests in `app/Http/Requests/` |
| No mime or file-size check on uploads | Content-based `File::image()->max(10 * 1024)`, SVG excluded |
| Anyone could open any upload, including unpublished ones | Private disk; images served only through an authorised route |
| Insecure error messages | Generic login failure; 404 instead of 403 everywhere |
| Duplicate `/account` routes | Removed |
| Tailwind loaded but unused / custom CSS without BEM | Custom CSS deleted; Tailwind utilities and theme tokens throughout |
| `app.js` loaded twice on `/game` | One entry point; Phaser split into its own lazy chunk |
| Two Google users with the same nickname collided | `availableUsername()` finds a free variant instead of a constraint violation and a 500 |
| `protected $fillable` mixed with `#[Fillable]` | `#[Fillable]` everywhere |
| No pagination | 12 per page on Community and Account |
| Images not deleted with the drawing | `destroy()` removes the file too |
| Register did not log you in | It does, and regenerates the session |
| No feedback on publish or delete | Toasts, via a shared flash prop |

One Google-login bug found along the way was worse than it looked: signing in with Google used to overwrite the whole user row, so someone who had registered with a password and later used Google had their password replaced with a random hash and their username rewritten — locked out permanently, since there is no password reset. It now only ever writes `google_id`.

## 4. Save the whole game, not just the picture *(5 Aug, `d7c07cb`)*

A migration added the four colours plus `speed` and `jump_height` to `saved_drawings`. Saving stores the session's colours and the sliders' current positions, so replaying a level — yours or anyone's from the community — skips colour picking entirely and starts exactly as its author tuned it. The engine reads the sliders' *initial* values when it starts, which is the mechanism that carries the saved feel into the game.

Older drawings keep working: they have no settings, so they still go through the eyedropper — and gain settings the first time their owner re-saves them.

Re-saving your own drawing updates it in place rather than creating a duplicate card. Saving a level you *played* rather than made copies the file, so each drawing owns its image — otherwise the original owner's delete would leave their picture on display under someone else's name.

## 5. Draw a level in the browser *(5 Aug, `d7c07cb`)*

A second way in that needs no paper, no camera and no colour picking: a canvas at the engine's exact world size, four fixed marker colours, brush size, eraser, undo, clear, and touch and stylus support.

Before it lets you play, the page runs **the game's own detector** over the canvas and refuses with a specific message if a platform, goal or player is missing — or drawn, but too small for the engine to see. Checking for coloured pixels was not enough: the detector ignores shapes under 300 px, so a small dot passed a naive check and produced a game with no player in it.

## 6. Dead code removed *(5 Aug, uncommitted)*

28 files, 27.2 MB, referenced by nothing: three copies of the old banner video (26.7 MB of it), the animated button and photo borders, the old logo and icons, and three stray source images. Only the five images the Home and About pages actually show are left.

Also removed: both scaffolded `ExampleTest` files (the home page is covered properly elsewhere), the unused half of `tests/Pest.php`, Laravel's demo `inspire` command, a commented-out import, and a factory state nothing called.

`levels:prune` cleared 220 orphaned uploads left behind by the old flow — files nothing referenced, because the sweeper it relied on needs a scheduler that was never set up on this machine.

## 7. Levels stay in the browser until saved *(5 Aug, uncommitted)*

The change described at the top of Part 1, and the largest structural one.

**Why:** at 500 uploads a day, the old flow wrote about 1.5 GB daily — almost all of it to be deleted again by a scheduled job that only runs if a cron entry exists. Not writing those files removes the orphan problem, the schedule and the cron dependency outright, rather than managing them.

**What went:** `/upload-level`, `/draw-level`, `/uploaded-level`, `UploadLevelController`, `UploadLevelRequest`, and the `uploadedLevel` session key.

**What arrived:** `resources/js/levelStore.js` (IndexedDB, with an in-memory fallback for browsers whose privacy mode refuses to open a database), the `replayDrawingId` session key, and `drawingId` on the save request.

`levels:prune` survives as a weekly safety net rather than a daily chore: the file and the row are now written together, and the file is deleted again if the row cannot be written, so an orphan should not be possible in the first place.

---

# Part 3 — Deliberately not done

**Shrinking images before upload.** The game renders at 1500×800, so a 12-megapixel photo has about 95% of its pixels thrown away — but a saved level is still stored at full camera size. Shrinking each one to the size the engine actually uses would take roughly 55 GB/year down to 2 GB/year. Not storing unsaved levels (change 7) fixes the *transient* pile; this would fix the permanent growth. It is a small follow-up: one canvas pass on the same Blob at the Save step.

**Object storage (S3 / Cloudflare R2).** The right answer at real scale, and cheap to adopt later — every read and write already goes through a single disk name. Premature now.

**Built-in authentication (Breeze / Fortify).** Authentication is hand-rolled, plus Socialite for Google. Swapping it is a dependency change and an open decision, not an oversight.

**Stock Laravel scaffolding.** The empty `AppServiceProvider`, the mail-service blocks in `config/services.php`, `Notifiable`, `email_verified_at`, the cache/jobs/password-reset tables. All unused, all standard — removing them would make the project *less* like a Laravel application.

**Automated end-to-end coverage of the level flow.** Now that the picture is held client-side, "choose a file → pick colours → play → save" cannot be exercised by a server-side test. Pest 4's browser plugin is not installed, so that path is verified by hand. This is a real gap, and the honest cost of change 7.

---

# Working on it

```bash
composer run dev            # server + queue + vite together
composer run setup          # install, .env, key, migrate, npm install, build

php artisan test --compact  # 63 tests
npm run test                # 54 tests (vitest — the pixel work and the level store)
npm run build

vendor/bin/pint --dirty     # required after touching PHP
```

`CLAUDE.md` holds the conventions and the details of each contract; this file is the narrative.

**To check the level flow by hand** (there is no automated test for it): upload a photo, pick colours, play — and confirm `storage/app/private/levels` is still empty. Then save (a file appears, one row), save again (still one row, "Drawing updated."), refresh mid-game (the level survives), and play a community level and save it (their file is copied, not shared).
