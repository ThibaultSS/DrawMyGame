import { describe, it, expect, beforeEach } from "vitest";

import { putLevel, getLevel, clearLevel } from "../levelStore.js";

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
