import { describe, it, expect } from "vitest";

import { isImageFile } from "../imageFile.js";

describe("isImageFile", () => {
    const file = (name, type) => new File(["contents"], name, { type });

    it("accepts an image", () => {
        expect(isImageFile(file("level.png", "image/png"))).toBe(true);
    });

    it("rejects a file that is not an image", () => {
        expect(isImageFile(file("clip.mov", "video/quicktime"))).toBe(false);
    });

    it("rejects a file with no type at all", () => {
        expect(isImageFile(file("mystery", ""))).toBe(false);
    });

    it("rejects nothing at all", () => {
        expect(isImageFile(null)).toBe(false);
    });

    it("ignores the case of the type", () => {
        expect(isImageFile(file("level.PNG", "IMAGE/PNG"))).toBe(true);
    });
});
