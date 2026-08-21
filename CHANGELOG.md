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

## Sharing a level

Publishing asks for a **title** and, optionally, a **description** — a gallery card with neither says nothing about what a level is. Publishing again is how those are edited; unpublishing takes the level out of the gallery but keeps the text, so putting it back does not mean writing it again.

Anyone signed in can **like or dislike** a level while playing it — one vote each, changeable, and clicking the same button again takes it back. You cannot vote on your own level. Signing in is what makes one-vote-per-person enforceable: it is a unique index on `(user, drawing)` rather than a promise.

The gallery can be **searched** by title or author and **sorted** by newest or most liked, where "most liked" means likes minus dislikes — so a divisive level does not outrank a quietly good one on raw likes alone.

## What the server keeps

**In the session:** the four colours, `gameSpeed`, `jumpHeight`, and `replayDrawingId` (which saved drawing, if any, is being played). Never the picture.

**In the database:** `saved_drawings` — the image path, the owner, `published`, the `title`/`description`, plus the whole game: four colour columns and `speed`/`jump_height`. All nullable, because drawings from before those migrations have none; `hasGameSettings()` is what tells the two apart. Rows are soft-deleted. `drawing_votes` holds one row per person per drawing, `value` being `1` or `-1`.

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

## 8. A community worth browsing *(9 Aug, uncommitted)*

The gallery was an image and an author's name per card, ordered newest first. Nothing said what a level was, nothing said whether it was any good, and once a level fell off the first page there was no way back to it.

**Titles and descriptions.** Publishing stopped being a one-click toggle and became two routes: `publish` carries a title (required) and an optional description and doubles as the edit, `unpublish` carries nothing and leaves the text alone. The form opens inside the card on the account page rather than in a dialog — no focus trap or escape-key handling to get wrong. Both columns are nullable, so the drawings published before this still work and simply read "Untitled".

**Likes and dislikes**, cast while playing a level. One vote per person, changeable, and clicking the same button again takes it back. You cannot vote on your own level, and voting needs an account — which is the point: a unique index on `(user, drawing)` makes one-vote-each a fact about the database rather than a hope about the controller. Anonymous voting would have reached more people but could be repeated by clearing cookies, so the counts would have meant nothing.

**Search and sorting.** The gallery takes a search over title *or* author name — with the `or` nested inside a closure, because left ungrouped it would escape the `published` filter and start showing unpublished levels to everyone — and a Newest / Most liked switch. "Most liked" ranks by likes minus dislikes rather than raw likes. The paginator uses `withQueryString()`, without which page two silently drops both.

A second test file, `tests/Feature/CommunityTest.php`, covers this: 21 tests across publishing rules, the voting rules above, search, ranking and query-string pagination.

## 9. Four fixes from playing it *(10 Aug, uncommitted)*

**The controls moved beside the game.** They sat under an 800-pixel canvas, so changing the speed meant scrolling away from what you were changing. They are now a column to the right of it, dropping back underneath on narrow screens where a side column would leave the game too small to play.

**Hazards became optional.** Colour picking demanded all four roles, so a level with nothing dangerous in it could not be started without inventing a danger. Three of them make a level; the hazard is now nullable. That touched more than the rule: `session()->has()` reports false for a null value, so `/game` had to ask for three colours or it would have bounced every hazard-less level back to the upload page — and `main.js` evaluates `window.hazardColor.replace(…)` before deciding whether there is anything to draw, which would have thrown at boot. Writing the tests also turned up a leak: an omitted hazard left the *previous* level's hazard colour in the session, because `validated()` omits a key that was never sent.

**Log out left the navigation** for the account page, where the rest of what you can do to your account now lives.

**An account section**, on `/account` above the drawings: change your username — it is the name on every community card, so the change follows the levels — change your password, or delete the account.

Deleting is confirmed by typing your **username**, not your password, because accounts created through Google were given a random one nobody knows; a password prompt would have left those people unable to delete their own account. For the same reason the password form is honestly labelled as not available to them.

What deletion keeps is the interesting part: **published levels stay**, credited to "Unknown publisher". They are already out in the community — other people have played and voted on them — so the author key became nullable with `nullOnDelete` rather than cascading. Unpublished drafts are private and go with the account, image files included. One consequence worth naming: with a nullable owner, a signed-out visitor's id is *also* null, so `isPlayableBy()` needed an explicit check or an ownerless private level would have matched every guest.

`tests/Feature/AccountTest.php` covers the three forms, what deletion keeps and removes, and that guest guard.

## 10. A home page worth landing on *(15 Aug, uncommitted)*

The landing page was a heading, one paragraph, one button and three long blocks of text. It gave no sense of what the site is, and it never mentioned `/draw` at all — the second way to make a level was reachable only from the nav.

**The banner video came back.** It had been dropped in the Blade → Vue port and was still sitting unused in `public/`, so it moved to `public/assets/banner.mp4` — the old name, `Banner_video (1).mp4`, needed URL-encoding for its space and parentheses every time it was written. It is 1920×1080 H.264, 48 seconds, 6.36 MB, and is shipped as-is rather than re-encoded.

Three things make that size and length safe. It is `muted`, without which no browser will autoplay it — and it does have an audio track. It is `preload="metadata"`, so first paint does not wait on 6 MB. And the hero sits on `bg-ink` with the copy already in white over a `bg-ink/50` scrim, so the page reads correctly before a single frame has arrived and stays readable over whatever frame is showing. A 48-second loop behind text is also exactly what `prefers-reduced-motion` is for: when it is set the video does not autoplay and gains its own controls instead, so it is still there for anyone who wants it.

**A second button.** The hero offers Upload *and* Draw. Drawing in the browser was reachable only from the nav before, even though it is the way in that skips colour picking entirely.

**A fourth section, "Play what others drew"** — the community explained in the same shape as the three that were already there: what the gallery is, that you can search and sort it, that voting needs an account and gives you one vote per level, that saving someone else's level hands you your own copy to re-tune while theirs stays untouched, and how publishing and unpublishing your own works. Its picture is still to come, so `SECTIONS` takes a null `image` and the section renders a dashed placeholder frame rather than a broken image.

**"How you play" spelled out.** The three arrow keys, then what winning and losing actually look like — confetti and a message, or a stop with Close and Retry and no lives to run out of — then the two sliders beside the game and the fact that saving keeps them, so the next person starts at the feel you settled on. It closes on the thing that surprises people: the physics follow your lines rather than a tidied-up version of them, so a shape you thought was closed but is not will let you fall straight through it.

The three original how-it-works sections are untouched, and no back end changed — the page is still a static `Route::inertia()`.

An earlier version of this page also carried a live strip of the best-liked community levels and a folded FAQ. Both were cut on review, and the `HomeController`, the model scopes and the home-page tests that fed the strip were reverted with them rather than left in place unused.

## 11. The About page, framed *(15 Aug, uncommitted)*

Presentation only — every word on the page is the one that was already there.

The page is now laid out **exactly** like the home page rather than merely near it: the same banner treatment, the same `gap-20` container, and the same alternating sections driven by a `SECTIONS` array instead of two hand-mirrored blocks of markup. A first attempt invented its own spacing scale and put hairline rules between the sections — details the home page does not have — which made the two pages look related rather than the same. The rules are gone.

The banner is the home hero without the video: a full-bleed black band, title centred in white. There is no artwork for it yet, so it carries the same dashed "Picture coming soon" marker as the community section, and dropping a picture in is one line — `BANNER.image` — after which the image fills the band under a scrim and the marker removes itself.

The old `<h1>About</h1>` is absorbed into the banner instead of repeated under it, and the opening paragraph sets as a lead. The two logo frames share a height, since a logo is centred in a padded frame rather than filling it like the home page's photographs, and without that the sections sat at two different heights.

One thing fixed in passing: `Phaser_Logo.png` is **3 MB** for a logo drawn at 128 px tall, and was loading eagerly. Both logos are now `loading="lazy"`. Re-encoding that file — it should be well under 100 KB — is still worth doing and is not done here.

---

## 12. Full-height banners, and a way down *(20 Aug, uncommitted)*

Both banners now fill the screen, so no text peeks out underneath and the picture gets the whole window. The height is `calc(100svh - 5rem)` rather than a round `100vh`: the `5rem` is the header the banner sits under, so the band ends *at* the fold instead of 4 rem past it, and `svh` rather than `vh` because a phone's address bar makes `100vh` taller than what you can actually see — with `vh` the bottom of the banner, and the arrow on it, would sit off-screen on exactly the devices that need the arrow most.

Which is the new problem a full-screen banner creates: it looks like the entire page. So there is now a **scroll cue** — an arrow that nudges up and down at the foot of the banner, and scrolls you to the first block of text when clicked. Scrolling yourself works as it always did; this only makes the page's length obvious.

It lives in `resources/js/Components/ScrollCue.vue` and both banners use it, so they cannot drift apart — and swapping the drawn chevron for artwork is one line, `ARROW.image`, for the whole site. `prefers-reduced-motion` is honoured twice over: the bounce is behind a `motion-safe:` variant, and the scroll jumps rather than glides.

The cue was asked for on the home page. It is on the About banner too, because that banner now hides its text in exactly the same way.

---

## 13. The About logo, and what the site stores *(20 Aug, uncommitted)*

**The About banner has its artwork.** Two things about the file decided how it is used, both checked rather than assumed. It is 891×388, so stretching it across a full-screen banner would have been a 2.3× upscale and visibly soft on the lettering — it is therefore capped at `max-w-xl`, below its own width, so it is only ever scaled down. And it is a JPEG of black lettering on white with no transparency, so dropped straight onto the black band it would have rendered as a white rectangle; it sits in a white panel instead, which is the same framing the two logos further down the page already use, and is what makes the white ground read as deliberate rather than as a mistake.

The heading went `sr-only` rather than being deleted. The logo already says the site's name, so showing both printed it twice — but a page still needs a heading for its document structure, so it stays in the markup and only leaves the screen.

**A cookie notice, and a `/cookies` page.** Deliberately *not* an Accept/Reject banner. This site sets two cookies, `drawmygame-session` and `XSRF-TOKEN`, and both are strictly necessary; there is no analytics, no advertising and no third-party script anywhere in `resources/`. Strictly necessary cookies are exempt from consent, so a banner offering a choice would have been offering one that does not exist. Instead there is a short note saying what is stored, and a page setting out all three things — the two cookies and the level store — with what each is for and how long it lasts, plus the detail that signing in with Google leaves its cookies on Google's domain and not this one.

Two small decisions inside it. The note sits **bottom left**, because `FlashToast` already owns the bottom right and a bar or a right-hand card would have sat under the toast the first time somebody saved a drawing. And the dismissal is kept in `localStorage` **rather than in a cookie** — recording that somebody read a notice about cookies by setting a cookie is exactly the kind of detail that makes the notice untrue. Both ends are wrapped in `try/catch`, since a privacy mode can refuse `localStorage` just as it can refuse the level store's IndexedDB; the honest failure is that the note appears again next visit.

The `/cookies` link is in the footer only. It is deliberately not in `NAV_LINKS`, which feeds the top nav as well.

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

php artisan test --compact  # 100 tests
npm run test                # 54 tests (vitest — the pixel work and the level store)
npm run build

vendor/bin/pint --dirty     # required after touching PHP
```

`CLAUDE.md` holds the conventions and the details of each contract; this file is the narrative.

**To check the level flow by hand** (there is no automated test for it): upload a photo, pick colours, play — and confirm `storage/app/private/levels` is still empty. Then save (a file appears, one row), save again (still one row, "Drawing updated."), refresh mid-game (the level survives), and play a community level and save it (their file is copied, not shared).
