# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

DrawMyGame turns a hand-drawn picture into a playable platformer. A user uploads an image, clicks on it to pick which colors mean *platform*, *goal*, *player*, and *hazard*, and a Phaser 4 / Matter.js scene converts those colored pixel regions into physics bodies.

The backend is a thin Laravel shell: it holds the level choices in the **session**, keeps *saved* drawings, and serves Inertia responses. It never sees a level that has not been saved — the browser holds that itself. The frontend is Vue 3 pages in `resources/js/Pages/` inside the `resources/js/Layouts/AppLayout.vue` shell; the single Blade file left is `resources/views/app.blade.php`, the Inertia root. All level parsing and gameplay happens client-side in `resources/js/game/`.

House style: white page, black ink, `#D9D9D9` sub-colour — expressed only through the theme tokens in `resources/css/site.css` (`bg-page`, `text-ink`, `border-sub`, `text-error`). Do not introduce other colours without asking.

## Commands

```bash
composer run dev            # server + queue listener + vite, concurrently
composer run setup          # install, .env, key, migrate, npm install, build
npm run dev                 # vite only (HMR host is drawmygame.test)
npm run build

composer run test                              # config:clear + full suite
php artisan test --compact --filter=test_user_can_login
php artisan test --compact tests/Feature/GameTest.php

vendor/bin/pint --dirty --format agent
```

**Saved** level images live on the **private** `local` disk (`storage/app/private/levels`) and are only served through `LevelImageController` at `route('drawings.image', $drawing)` (published → everyone, unpublished → owner only). A level that has not been saved has no URL at all: it exists only as a Blob in the browser. There are no public storage URLs for levels, so `storage:link` is not part of this flow.

## Request flow (the core feature)

The image is held **client-side** until it is saved; only the choices about it go through the **session**. `resources/js/levelStore.js` is that holding place — `putLevel`/`getLevel`/`clearLevel` over IndexedDB (with an in-memory fallback if a privacy mode refuses to open a database). Pages turn the Blob into an object URL themselves and revoke it on unmount.

1. `GET /upload` (`Pages/Upload.vue`) checks the chosen file locally (not SVG, ≤10 MB, and `createImageBitmap` can decode it), calls `putLevel(file)` and visits `/game-setting`. **Nothing is posted.**
2. `GameSettingController::show` renders `Pages/GameSetting.vue` (the eyedropper) with `image` = the replayed drawing's URL, or **null** for a browser-held level — in which case the page reads the Blob from the store, and visits `/upload` if there is none
3. `POST /start-game` → `GameSettingController::store` (`StartGameRequest`: platform, goal and player are required `#rrggbb`; **the hazard is nullable**) writes the colours to the session, forgets `gameSpeed`/`jumpHeight`/`replayDrawingId`, and redirects to `/game`. `hazardColor` is set explicitly rather than through `validated()`, which omits a key that was never sent — otherwise an earlier level's hazard would linger into one that has none.
4. `GET /game` (`GameController`) redirects to `/upload` unless the session holds **the three required colours** — asking for the hazard here would bounce every hazard-less level, because `session()->has()` reports false for a null value — then renders `Pages/Game.vue` with `levelImage` (the saved drawing's URL, or **null**), `drawingId`, the colours, the slider positions and the vote state as **props**; `Game.vue` resolves the image from the prop or the store, copies the inputs onto `window.*` and boots the engine — that `window.*` contract is the only interface between the page and `main.js`
5. `POST /save-drawing` is **the only route that accepts a file**, and the first moment the server keeps anything. It sends `levelImage` for a browser-held level, or `drawingId` for one the server already has (re-tuning your own, or copying someone else's). Afterwards `session('replayDrawingId')` names the new drawing, so the page's next Save updates it instead of duplicating
6. `/play/{drawing}` replays a saved drawing (published, or unpublished-but-yours; anything else 404s). It sets `session('replayDrawingId')`; a drawing with game settings also fills in colours plus `gameSpeed`/`jumpHeight` and goes straight to `/game`, while a pre-settings drawing clears the old game's keys and detours through `/game-setting`
7. `/draw` (`DrawnLevelController`) is the second way in: draw the level on a canvas in the browser. The palette is a controller constant passed to the page as a prop, so the page calls `putLevel(blob)` and posts those same colours to `/start-game` — no eyedropper, and no upload

Consequence: changing a session key or prop name means changing it in the controller/route, the page component, and (for the game) `main.js`.

## Game engine (`resources/js/game/main.js`)

A Phaser scene using module-level globals (`player`, `outlines`, `goalOutlines`, `playerOutline`, `hazardOutlines`) and plain `preload`/`create`/`update` functions — not a class-based scene. The pixel work lives in three siblings: `config.js` (the `WORLD` size and the `DETECTION` constants), `colorDetection.js` and `shapeTracing.js`, all covered by vitest under `game/__tests__/`.

The image→level pipeline in `imageToLevelData()`:

- `detectShapes` (`colorDetection.js`) finds the connected shapes for all four colours in **one pass**, tracking visited pixels as one bit per colour. `DETECTION.colorTolerance` (70) and `minShapeSize` (300 px) are the main knobs when shapes are over- or under-detected; `maxShapeSize` (100 000) bails out of a flood fill that is swallowing the page.
- `traceBoundary` (`shapeTracing.js`) — Moore-neighbour tracing, so the ring never crosses itself, which poly-decomp needs
- `simplifyOutline` — Douglas-Peucker at `simplifyEpsilon`, escalating epsilon until the ring is under `maxColliderVertices` (64), then *refining* it again if the shape has collapsed to a line (a thin diagonal bar is thinner than epsilon)
- `fitToWorld` scales **both axes by the same factor** and centres the result, so a 4:3 photo is not squashed into the 1500×800 world; `matter.world.setBounds` is set to that fitted rectangle, not the whole canvas, so the player cannot walk into the empty bars beside the drawing

`createObjects()` draws the traced polygon and `createShapeBodies()` gives it a **real polygon collider** via `matter.add.fromVertices`, falling back to a bounding box only when decomposition fails — so collisions follow the drawn outline.

Goal and hazard bodies are identified by `body.label` (`"goal"` / `"hazard"`), and the label is written to **every part** of the body: poly-decomp splits a concave shape into a compound body and Matter reports collisions on the parts. The `collisionstart` handler compares against `body.parent` for the same reason. Any contact with the player sets `canJump = true` (there is no ground-normal check).

The player is the **largest** player-colored shape; its Matter body is a rectangle with `setInertia(Infinity)` so it can't tilt, and `update()` redraws the player polygon each frame by offsetting the original outline from the body position.

`window.gamePaused` gates `update()`. The engine does not boot on import: `main.js` exports `bootGame()`, which resets the module-level state and returns the `Phaser.Game`; `Game.vue` calls it on mount (after setting the `window.*` inputs) and `game.destroy(true)` on unmount. The engine also reaches into the page by element id — `game-container`, `loading-screen`, `popup`, `popup-message`, `speedSlider`, `jumpSlider` — so those ids in `Game.vue` are part of the contract. `canvas-confetti` is a CDN script injected by `Game.vue`; `showPopup` in `main.js` fires it via the global `confetti`.

## Frontend wiring

- Vite entrypoints are only `resources/css/site.css` and `resources/js/app.js` (the hand-written Inertia bootstrap — no `@inertiajs/vite` plugin). Pages resolve via `import.meta.glob("./Pages/**/*.vue")`.
- Phaser has no entry of its own: `Game.vue` imports `../game/main.js` lazily on mount, so Vite splits the engine into a chunk (~1.4 MB) that only the game page downloads.
- The Vue plugin sets `transformAssetUrls: { base: null, includeAbsolute: false }` — static `/assets/...` paths in templates are served from `public/` by Laravel and must not be treated as modules, or the build fails. That includes `/assets/banner.mp4`, the home page's hero video: it is `muted` (browsers refuse to autoplay sound), `preload="metadata"` so 6 MB does not block first paint, and the hero sits on `bg-ink` so the white copy is readable before a frame arrives. `Home.vue` reads `prefers-reduced-motion` and, when it is set, drops `autoplay` and gives the video its own controls instead. The page is otherwise all copy — its `SECTIONS` array drives the alternating image/text blocks, and an entry with a null `image` renders a dashed placeholder frame instead of a broken image. Both banners (Home and About) fill the viewport at `calc(100svh - 5rem)` — the 5rem is the header above them, and `svh` rather than `vh` so a phone address bar cannot push the foot of the banner off-screen. That hides everything below the fold, so both carry `Components/ScrollCue.vue`: a `motion-safe:` bouncing arrow that scrolls to `#page-content`. That id on each page's container is the contract with the component.
- Styling is Tailwind v4 with the house-style `@theme` tokens in `site.css`. No component-level `<style>` blocks except `Game.vue`'s canvas scaling rule.

## Backend notes

- Every route is named and there are no closures in `routes/web.php`: static pages use `Route::inertia()`, everything else goes to a controller — single-action `__invoke` (`GameController`, `DrawnLevelController`, `LevelImageController`, `LogoutController`, `LoginController`, `RegisteredUserController`) or multi-method (`SavedDrawingController`, `GameSettingController`, `GoogleController`). Validation lives in Form Request classes under `app/Http/Requests/`.
- Community and Account paginate (12 per page); the paginator object is passed to Vue whole, and `Components/Pagination.vue` renders its `links`. Shared UI lives in `resources/js/Components/` (`Pagination`, `FlashToast` — the latter sits in `AppLayout` and turns any `back()->with('message', ...)` into a toast). The gallery also takes `?search=` (title **or** author username) and `?sort=newest|liked` — the search's `orWhere` is nested inside a closure so it cannot escape the `published` filter, and the paginator uses `withQueryString()` or page two would drop both.
- Publishing is two routes, not a toggle: `drawings.publish` carries a title (required, ≤ 80) and an optional description through `PublishDrawingRequest` and doubles as the edit, `drawings.unpublish` carries nothing and leaves the text in place. Titles are nullable, so drawings published before that existed show as "Untitled".
- `drawing_votes` holds one like or dislike per person per drawing — `value` is `1`/`-1` so a ranking is a plain `sum(value)`, and a **unique index on `(user_id, saved_drawing_id)`** is what makes "one vote each" true in the database and not just in `DrawingVoteController`. Voting requires an account, only works on published drawings (404 otherwise), and is refused on your own (403 — the drawing is public, so its existence is not a secret). Voting the same way twice deletes the row, which is how a vote is taken back.
- Saving a level you *played* rather than made copies the file server-side, so each drawing owns its image — the browser is never asked to upload an image the server already has. Deleting a drawing soft-deletes the row and removes the file; the `whereKeyNot` guard in `destroy()` is what stops the just-trashed row from matching itself.
- `SavedDrawingController::store` writes the file and the row together and deletes the file if the insert throws, so an image nothing points at should not exist. `levels:prune` (scheduled **weekly**) is the safety net for the ones that do anyway — a crash between the two writes, or a database restored from an older backup. `POST /save-drawing` and `POST /register` are throttled, each in its own **named** bucket (`throttle:20,1,uploads`) — an unnamed throttle shares one counter per visitor across the whole site.
- Google sign-in only ever writes `google_id` to an existing account. It used to `updateOrCreate` the whole payload, which replaced a password user's password with a random hash and renamed them — an unrecoverable lockout, since there is no password-reset flow.
- **A hazard colour is optional** — a level with nothing dangerous is still a level. `hasGameSettings()` therefore tests only three colours, and `main.js` guards on `window.hazardColor` before both detection and drawing: `createObjects(…, window.hazardColor.replace(…))` evaluates the argument first, so a null colour throws at boot even with no hazards found.
- `saved_drawings.user_id` is **nullable with `nullOnDelete`**. Deleting an account keeps whatever it published — the level is already out in the community — and `community()` credits it to "Unknown publisher" via `$drawing->user?->username ?? …`. `isPlayableBy()` needs its `$userId !== null` check because a signed-out visitor's id is also null and would otherwise match an ownerless row. Unpublished drafts are deleted with the account by `AccountController`, files and all.
- Account management lives on `/account` beside the drawings: `account.username`, `account.password` (both `PATCH`) and `account.destroy` (`DELETE`). Deleting is confirmed by typing your **username**, not your password — Google accounts were given a random one nobody knows, so a password prompt would make deletion impossible for them, and for the same reason the password form is unusable by those accounts. Logging out is here too, not in the nav.
- `SavedDrawing` uses `SoftDeletes` and stores the whole game alongside the image, plus `title`/`description` for the gallery: the four colours plus `speed`/`jump_height` (all nullable — `hasGameSettings()` distinguishes pre-settings drawings, which still re-pick colours on replay). Saving posts the sliders' current values; `main.js` reads the sliders' initial values in `create()`, which is how a replay starts at the saved feel. Models declare mass assignment with the `#[Fillable]` attribute, not `protected $fillable`.
- Ownership is enforced inline with `->where('user_id', Auth::id())->firstOrFail()`, not policies.
- Shared Inertia props live in `HandleInertiaRequests::share()`: `auth.user` (id/username/initials only — everything shared is readable in the page source) and `flash.message`, which the Account and Game pages surface as a toast after `back()->with('message', ...)`.
- Auth is hand-rolled (no Breeze/Fortify) plus Socialite for Google.
- The site sets exactly two cookies, both strictly necessary: the session and `XSRF-TOKEN`. There is no analytics and no third-party script, which is why `Components/CookieNotice.vue` is an informational note rather than an Accept/Reject banner — strictly necessary cookies need no consent, and `/cookies` lists all three stored things (those two plus the level store). Its dismissal lives in `localStorage`, not in a cookie, and is `try/catch`-guarded like the level store, because a privacy mode can refuse it. **Adding anything that is not strictly necessary — analytics above all — means this becomes a real consent gate and `/cookies` has to say so first.**
- Tests are written as PHPUnit classes in `tests/Feature/GameTest.php` even though Pest 4 is installed — follow the existing class style when extending that file.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/socialite (SOCIALITE) - v5
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
