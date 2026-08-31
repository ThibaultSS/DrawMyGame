# Changelog

Work on DrawMyGame, newest first. Dates are when the work was done.

## The engine and the pages

**The game engine was split up.** `main.js` had grown into one long file. The
pixel work moved into `colorDetection.js` and `shapeTracing.js`, with the world
size and detection constants in `config.js`. Those two modules are pure
functions over pixel data, which is what made them testable.

**Blade became Inertia and Vue.** Every page is now a Vue component in
`resources/js/Pages/`. The only Blade file left is `app.blade.php`, which is the
Inertia root. Styling is Tailwind with four colour tokens in `site.css`.

**Drawing in the browser.** `/draw` is a second way in: draw the level on a
canvas instead of photographing paper. The colours are fixed defaults, so it
skips the colour picking step.

## Levels

**A level stays in your browser until you save it.** Most levels are played once
and never kept, so uploading every one of them meant writing files that were
only deleted again. The picture now lives in IndexedDB and is uploaded when you
press Save, which is the first moment the server has a reason to keep it.
`POST /save-drawing` is the only route that accepts a file.

**The whole game is saved, not just the picture.** The four colours and the
speed and jump sliders are stored with the drawing, so replaying one starts
exactly as its author tuned it.

**Hazards are optional.** A level with nothing dangerous in it is still a level.

**One picture is one file.** Images are stored under a hash of their own
contents, so a level saved by fifty people is one file with fifty rows pointing
at it. `levels:dedupe` collapsed the duplicates that already existed, and
`levels:prune` removes files nothing points at.

## Community

**A gallery of published levels**, searchable by title or author and sortable by
newest or best liked. Publishing takes a title and an optional description.

**Likes and dislikes**, one per person per level, enforced by a unique index
rather than only by the controller. You cannot vote on your own.

**Completions and times.** Finishing a level records your time and counts
towards "beaten by 12 of 40". Each level has a top ten.

**Saving someone else's level keeps it theirs.** It used to copy the drawing
into your account, which made you its owner and let you publish it under your
own name. It is a favourite now, holding your own speed and jump for their
level.

## Accounts

Username, password and account deletion all live on `/account`. Deleting is
confirmed by typing your username rather than your password, because accounts
created through Google were given a random one nobody knows.

Deleting an account keeps whatever it published, credited to "Unknown
publisher", and removes its unpublished drafts along with their files.

Google sign-in only ever adds a `google_id` to an existing account. It used to
overwrite the whole record, which replaced a password user's password and
renamed them.

## Pages and presentation

The home and about pages open on a full-height banner, with a bouncing arrow
that scrolls to the content because the banner hides everything below it. The
home page explains the loop; the about page carries the project logo.

The game page puts the controls beside the canvas rather than under it, and the
level can be played on a phone with on-screen buttons.

There is a cookie notice and a `/cookies` page. The site sets two cookies, both
strictly necessary, so it explains rather than asks.

## Robustness

- Usernames are 3 to 30 characters of letters, digits, hyphen and underscore.
  Google display names are sanitised to the same rule before being stored.
- Both ways in check that the drawing will actually parse before starting, so a
  level with no detectable player says so instead of loading broken.
- Colours chosen on the drawing page are checked against each other and against
  the paper, because colours closer than the detector's tolerance overlap.
- Uploads are validated by content, capped at 10 MB, and SVG is excluded.
- Saved images live on a private disk and are served only through a controller
  that checks published-or-owner on every request.
- Failed saves, refused favourites and missing levels all say so instead of
  quietly doing nothing.

## Still to do

**Shrinking images before upload.** The game renders at 1500x800, so a
twelve-megapixel photo has most of its pixels thrown away, but a saved level is
still stored at full camera size. One canvas pass at the save step would fix it.

**A cap on how many shapes a level may produce.** A very noisy photo can turn
into hundreds of physics bodies and make the game stutter.

**Automated coverage of the level flow.** Now that the picture is held in the
browser, choosing a file, picking colours and playing cannot be exercised by a
server-side test. That path is checked by hand.
