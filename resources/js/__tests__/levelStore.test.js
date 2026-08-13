import { describe, it, expect, beforeEach } from "vitest";

import { putLevel, getLevel, clearLevel } from "../levelStore.js";

/**
 * Node has no IndexedDB, so every call here falls through to the module's
 * in-memory fallback — the path a browser only takes when a privacy mode
 * refuses to open a database.
 *
 * That still pins the contract the pages depend on: a blob goes in, the same
 * blob comes back, and clearing empties it. The IndexedDB path itself is only
 * exercised in a real browser (see the manual walkthrough), because covering it
 * here would mean adding fake-indexeddb as a dependency.
 */
describe("levelStore", () => {

    const level = () => new Blob(["a drawing"], { type: "image/png" });

    beforeEach(async () => {
        await clearLevel();
    });

    it("has nothing to play before anything is stored", async () => {
        expect(await getLevel()).toBe(null);
    });

    it("gives back the level that was put in", async () => {
        const blob = level();

        await putLevel(blob);

        expect(await getLevel()).toBe(blob);
    });

    it("keeps only the most recent level", async () => {
        const replacement = new Blob(["a second drawing"], { type: "image/png" });

        await putLevel(level());
        await putLevel(replacement);

        expect(await getLevel()).toBe(replacement);
    });

    it("has nothing to play once the level is cleared", async () => {
        await putLevel(level());
        await clearLevel();

        expect(await getLevel()).toBe(null);
    });

});
