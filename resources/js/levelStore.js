const DB_NAME = "drawmygame";
const DB_VERSION = 1;
const STORE_NAME = "level";

const KEY = "current";

let fallback = null;
let useFallback = false;

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (! db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME);
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
        request.onblocked = () => reject(new Error("IndexedDB is blocked by another tab."));
    });
}

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
