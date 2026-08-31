# DrawMyGame

Turn a hand-drawn picture into a playable platformer.

You draw a level on paper, take a photo of it, and pick which colours mean
*platform*, *goal*, *player* and *hazard*. The browser reads the photo pixel by
pixel, turns each area of colour into a physics body, and you play it with the
arrow keys. You can also skip the paper and draw the level straight onto a
canvas in the browser.

Levels you like can be saved to your account, published to a community gallery,
liked or disliked, and timed against everyone else who has finished them.

Built as a bachelor's project for Multimedia & Creative Technologies at KdG.

## Requirements

- PHP 8.3 or newer
- Composer
- Node 20 or newer
- MySQL (SQLite is used for the tests)

## Setup

```bash
composer run setup
```

That installs the PHP and Node dependencies, copies `.env`, generates the app
key, runs the migrations and builds the front end.

To work on it:

```bash
composer run dev
```

This runs the PHP server, the queue listener and Vite together. The site is at
`https://drawmygame.test` when using Laravel Herd.

## Commands

```bash
composer run dev      # server, queue and Vite together
composer run test     # the PHP test suite
npm run dev           # Vite only
npm run build         # build the front end
npm run test          # the JavaScript test suite
vendor/bin/pint       # format the PHP
```

There are two scheduled or one-off console commands for the level images:

```bash
php artisan levels:prune             # delete images no drawing points at
php artisan levels:dedupe --dry-run  # collapse duplicate images into one file
```

## How a level is built

The whole pipeline runs in the browser, in `resources/js/game/`:

1. `colorDetection.js` walks the photo once and finds the connected areas of
   each chosen colour. Anything smaller than 300 pixels is treated as noise.
2. `shapeTracing.js` walks the edge of each area to get its outline, then
   simplifies it to something the physics engine can use.
3. `main.js` scales the outlines to the game world, turns them into Matter.js
   bodies and runs the Phaser scene.

`roleCheck.js` runs the same detector before a level starts, so a drawing that
would produce no player says so instead of loading a broken level.

## Where a level lives

**A level you are playing is held in your browser, not on the server.** It goes
into IndexedDB when you choose or draw it (`resources/js/levelStore.js`) and is
only uploaded when you press Save. Most levels are played once and never kept,
so uploading every one of them meant writing files that were only ever deleted
again.

Two things follow from that:

- `POST /save-drawing` is the only route that accepts a file.
- A level that has not been saved has no URL. It cannot be linked to and it is
  not on the disk.

Saved images live on the private disk in `storage/app/private/levels`. They are
never served directly. `LevelImageController` is the only way to reach one, and
it checks on every request that the drawing is published or belongs to you.

## Implementation notes

A few things in here are not obvious from the code:

- **Colours need to be far apart.** The detector matches within a Euclidean RGB
  distance of 70, so two colours closer than that overlap and a platform would
  also count as a hazard. The drawing page checks this before it lets you start,
  and also checks each colour against the white of the paper.
- **`/game` asks for three colours, not four.** Hazards are optional, and
  `session()->has()` reports false for a null value, so asking for the hazard
  would send every level without one back to the upload page.
- **One picture is one file.** Images are stored under a hash of their own
  contents, so a level saved by fifty people is one file with fifty rows
  pointing at it. A file is deleted once no drawing still names it.
- **Times are measured in the browser**, because the game runs there. They are
  bounded to something sane on the way in, but they cannot be verified.

## Tests

143 PHP tests and 73 JavaScript tests.

```bash
composer run test
npm run test
```

The PHP tests cover the routes, validation, ownership and the console commands.
The JavaScript tests cover the pixel work: colour detection, shape tracing and
the role check.

The one thing without automated coverage is the level flow itself, from choosing
a file to playing it. The picture never reaches the server until it is saved, so
there is nothing server-side to assert against.

## Some choices worth explaining

**Authentication.** The login, registration and logout controllers are written
by hand, but they use Laravel's authentication rather than replacing it:
`Auth::attempt` with the session guard, the `hashed` cast on the password
column, `Password::defaults()` for the strength rules, the `auth` and `guest`
middleware on the routes, and session regeneration on login and registration.
Failed logins are rate limited per email *and* per IP, so guessing at one
account cannot lock someone out from elsewhere, and they never say whether it
was the email or the password that was wrong.

What is not used is a starter kit like Breeze. Breeze generates the auth pages
and controllers for you; it is a scaffolding tool rather than the authentication
system. Writing those few controllers by hand kept the pages consistent with the
rest of the site and made room for Google sign-in through Socialite alongside
them.

**Inertia instead of an API.** The pages are Vue components rendered through
Inertia, so there is no separate API layer to keep in step with the front end.
Controllers return pages with props, and validation errors come back to the same
form without a reload.

**Tailwind rather than custom CSS.** The only stylesheet is
`resources/css/site.css`, which defines four colour tokens and nothing else.
Everything else is utility classes in the components, so there is no second
place where a style can live.

**Anything you may not see is a 404, not a 403.** An id that belongs to someone
else is indistinguishable from one that does not exist, so ids cannot be probed.
The exception is acting on a published level that is not yours, such as voting
on your own drawing: that is a 403, because the level is public and its
existence is not a secret.
