# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

DrawMyGame turns a hand-drawn picture into a playable platformer. A user uploads an image, clicks on it to pick which colors mean *platform*, *goal*, *player*, and *hazard*, and a Phaser 4 / Matter.js scene converts those colored pixel regions into physics bodies.

The backend is a thin Laravel shell: it stores the upload, holds the level choices in the **session**, and renders Blade views. All level parsing and gameplay happens client-side in `resources/js/game/main.js`.

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

Uploads are served through `asset('storage/...')`, so `php artisan storage:link` must have been run or every level image 404s.

## Request flow (the core feature)

State is threaded through the **session**, not the database:

1. `GET /upload` → form posts to `POST /upload-level`
2. `UploadLevelController` stores the file on the `public` disk under `levels/` and puts the path in `session('uploadedLevel')`, then renders `gameSetting`
3. `gameSetting.blade.php` runs an inline-JS eyedropper: it draws the image to an offscreen canvas and reads the clicked pixel into four hidden hex inputs
4. `POST /start-game` → `GameSettingController` writes `platformColor`, `goalColor`, `playerColor`, `hazardColor` to the session and redirects to `/game`
5. `game.blade.php` echoes those session values into `window.levelImage` / `window.platformColor` / … — this is the **only** interface between PHP and the game engine
6. `/play/{id}` re-enters the same flow for a saved drawing by setting `session('uploadedLevel')` from the `SavedDrawing` record

Consequence: the game page is meaningless without prior session state, and changing a session key name means changing it in the controller, the Blade `window.*` block, and `main.js`.

## Game engine (`resources/js/game/main.js`)

A single 824-line Phaser scene using module-level globals (`player`, `outlines`, `goalOutlines`, `playerOutline`, `hazardOutlines`) and plain `preload`/`create`/`update` functions — not a class-based scene.

The image→level pipeline in `imageToLevelData()`:

- `matchesColor` — RGB euclidean distance with `tolerance = 70`. This tolerance and the `MIN_SIZE = 300` pixel floor in `getConnectedShapes` are the main knobs when shapes are over- or under-detected.
- `getConnectedShapes` — iterative 4-way flood fill, bails at 100 000 px per shape
- `getOutline` → `traceOutline` (nearest-neighbour ordering, O(n²)) → `simplifyOutline(traced, 8)`
- Coordinates are scaled by `1500 / source.width` and `800 / source.height` — the canvas is a fixed 1500×800 and `matter.world.setBounds` matches it.

Important asymmetry: **visuals and collision geometry are different shapes.** `createObjects()` draws the traced polygon, but `createRectangleBodies()` adds an axis-aligned bounding-box Matter body. Collisions therefore fire on the bounding box of a drawing, not its outline.

Goal and hazard bodies are identified by `body.label` (`"goal"` / `"hazard"`) set in `createRectangleBodies`, and checked in the `collisionstart` handler. Any contact with the player sets `canJump = true` (there is no ground-normal check).

The player is the **largest** player-colored shape; its Matter body is a rectangle with `setInertia(Infinity)` so it can't tilt, and `update()` redraws the player polygon each frame by offsetting the original outline from the body position.

`window.gamePaused` gates `update()`. Note `showPopup` exists twice — once in `main.js` (module scope, fires confetti) and once as a global in `game.blade.php`; `canvas-confetti` is loaded from a CDN there.

## Frontend wiring

- Vite entrypoints are only `resources/css/app.css` and `resources/js/app.js`; `app.js` does nothing but `import './game/main'`.
- `layouts/app.blade.php` includes the CSS but has `app.js` **commented out** — `game.blade.php` calls `@vite('resources/js/app.js')` itself, so Phaser loads only on the game page. Adding JS to `app.js` makes it game-page-only.
- `vite.config.js` sets `buildDirectory: '../../www/build'`, so built assets land outside `public/` (deploy host layout). Don't "fix" this to `build` without checking the deploy target.
- Styling is a mix of Tailwind v4 and hand-written CSS in `resources/css/app.css`, plus `<style>` blocks inside individual views. Animated PNG-frame borders (`.button-border`, `.photo-border`) are driven by `setInterval` swapping a `--border-frame` CSS variable.

## Backend notes

- Controllers are a mix of single-action `__invoke` (`UploadLevelController`, `GameSettingController`, `LoginController`, `RegisteredUserController`) and resourceful (`SavedDrawingController`, `GoogleController`).
- `SavedDrawing` uses `SoftDeletes` and only stores `user_id` / `image_path` / `published` — colors and gameplay settings are never persisted, so a replayed level requires re-picking colors.
- Ownership is enforced inline with `->where('user_id', Auth::id())->firstOrFail()`, not policies.
- `/account` is registered twice in `routes/web.php` (unguarded and inside the `auth` group); the guarded registration wins.
- Auth is hand-rolled (no Breeze/Fortify) plus Socialite for Google.
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
