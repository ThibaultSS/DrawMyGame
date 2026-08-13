/**
 * The level the browser is currently playing, held client-side.
 *
 * Uploading used to write a file to the server before anyone knew whether it
 * was worth keeping, and roughly nine in ten never were — so the disk filled
 * with images that only a scheduled sweep removed. The image now stays in the
 * browser and is uploaded when Save is pressed, which is the first moment the
 * server has a reason to keep it.
 *
 * IndexedDB rather than a module-level variable: Inertia keeps one JS context
 * across visits, so a variable would survive /upload -> /game-setting -> /game,
 * but not a refresh — and losing a level to F5 is worse than the problem being
 * solved here.
 *
 * Blobs go in, blobs come out. Turning one into a URL is the caller's job,
 * because only the caller knows when to revoke it again.
 */

const DB_NAME = "drawmygame";
const DB_VERSION = 1;
const STORE_NAME = "level";

// Only one level is ever in play, so the record always has the same key.
const KEY = "current";

/**
 * Set when IndexedDB refuses to open, which some privacy modes do. The level
 * then lives in memory for the rest of the page's life: it survives navigation
 * but not a refresh, which is a better failure than not being able to play.
 */
let fallback = null;
let useFallback = false;

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            // Guarded because onupgradeneeded also fires on a version bump,
            // when the store is already there.
            if (! db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME);
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
        request.onblocked = () => reject(new Error("IndexedDB is blocked by another tab."));
    });
}

/**
 * Runs one transaction and resolves with its request's result.
 *
 * Resolving on the transaction rather than on the request matters for writes:
 * a request succeeds as soon as the value is queued, while the transaction
 * completing is what says the data is actually durable.
 */
async function transact(mode, run) {
    const db = await openDatabase();

    try {
        return await new Promise((resolve, reject) => {
            const transaction = db.transaction(STORE_NAME, mode);
            const request = run(transaction.objectStore(STORE_NAME));

            transaction.oncomplete = () => resolve(request.result ?? null);
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error);
        });
    } finally {
        db.close();
    }
}

/** Replaces whatever level was being played with this one. */
export async function putLevel(blob) {
    if (useFallback) {
        fallback = blob;

        return;
    }

    try {
        await transact("readwrite", (store) => store.put(blob, KEY));
        fallback = null;
    } catch {
        useFallback = true;
        fallback = blob;
    }
}

/** The level being played, or null when there is none. */
export async function getLevel() {
    if (useFallback) {
        return fallback;
    }

    try {
        return await transact("readonly", (store) => store.get(KEY));
    } catch {
        useFallback = true;

        return fallback;
    }
}

/** Forgets the level, e.g. once it has been saved or replaced by a saved one. */
export async function clearLevel() {
    fallback = null;

    if (useFallback) {
        return;
    }

    try {
        await transact("readwrite", (store) => store.delete(KEY));
    } catch {
        useFallback = true;
    }
}
