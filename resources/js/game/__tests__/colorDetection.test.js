import { describe, it, expect } from "vitest";

import { hexToRgb, matchesColor, detectShapes } from "../colorDetection.js";
import {
    createImage,
    legacyGetConnectedShapes,
    normaliseShapes,
    BLACK,
    RED,
    GREEN,
    BLUE
} from "./helpers.js";

describe("hexToRgb", () => {

    it("reads a hex colour with a hash", () => {
        expect(hexToRgb("#ff8000")).toEqual({ r: 255, g: 128, b: 0 });
    });

    it("reads a hex colour without a hash", () => {
        expect(hexToRgb("00ff00")).toEqual({ r: 0, g: 255, b: 0 });
    });

});

describe("matchesColor", () => {

    const image = createImage(4, 4).fillRect(0, 0, 4, 4, { r: 100, g: 100, b: 100 });

    it("recognises an exact match", () => {
        expect(matchesColor(1, 1, image.pixels, 4, { r: 100, g: 100, b: 100 }, 10)).toBe(true);
    });

    it("accepts a difference within the tolerance", () => {
        expect(matchesColor(1, 1, image.pixels, 4, { r: 105, g: 100, b: 100 }, 10)).toBe(true);
    });

    it("rejects a difference outside the tolerance", () => {
        expect(matchesColor(1, 1, image.pixels, 4, { r: 200, g: 100, b: 100 }, 10)).toBe(false);
    });

    it("is exclusive at exactly the tolerance", () => {
        // Distance is exactly 10, and the comparison is strictly less than.
        expect(matchesColor(1, 1, image.pixels, 4, { r: 110, g: 100, b: 100 }, 10)).toBe(false);
    });

});

describe("detectShapes", () => {

    const options = { tolerance: 40, minShapeSize: 4, maxShapeSize: 100000 };

    it("keeps two separate blobs of the same colour apart", () => {

        const image = createImage(40, 20)
            .fillRect(2, 2, 6, 6, BLACK)
            .fillRect(20, 2, 6, 6, BLACK);

        const result = detectShapes(
            image.pixels, image.width, image.height,
            [{ key: "platform", color: BLACK }],
            options
        );

        expect(result.platform).toHaveLength(2);
        expect(result.platform[0]).toHaveLength(36);
        expect(result.platform[1]).toHaveLength(36);

    });

    it("joins blobs that touch into one shape", () => {

        const image = createImage(40, 20)
            .fillRect(2, 2, 6, 6, BLACK)
            .fillRect(8, 2, 6, 6, BLACK);

        const result = detectShapes(
            image.pixels, image.width, image.height,
            [{ key: "platform", color: BLACK }],
            options
        );

        expect(result.platform).toHaveLength(1);
        expect(result.platform[0]).toHaveLength(72);

    });

    it("assigns every shape to the right colour", () => {

        const image = createImage(60, 30)
            .fillRect(2, 2, 8, 8, BLACK)
            .fillRect(20, 2, 8, 8, RED)
            .fillRect(38, 2, 8, 8, GREEN);

        const result = detectShapes(
            image.pixels, image.width, image.height,
            [
                { key: "platform", color: BLACK },
                { key: "goal", color: RED },
                { key: "player", color: GREEN }
            ],
            options
        );

        expect(result.platform).toHaveLength(1);
        expect(result.goal).toHaveLength(1);
        expect(result.player).toHaveLength(1);

    });

    it("returns an empty list for a colour that is not in the image", () => {

        const image = createImage(40, 20).fillRect(2, 2, 6, 6, BLACK);

        const result = detectShapes(
            image.pixels, image.width, image.height,
            [{ key: "platform", color: BLACK }, { key: "hazard", color: BLUE }],
            options
        );

        expect(result.hazard).toEqual([]);

    });

    it("ignores a shape smaller than minShapeSize", () => {

        const image = createImage(40, 20)
            .fillRect(2, 2, 6, 6, BLACK)
            .fillRect(20, 2, 1, 1, BLACK);

        const result = detectShapes(
            image.pixels, image.width, image.height,
            [{ key: "platform", color: BLACK }],
            options
        );

        expect(result.platform).toHaveLength(1);

    });

    it("stops a runaway shape at maxShapeSize", () => {

        const image = createImage(40, 20).fillRect(0, 0, 40, 20, BLACK);

        const result = detectShapes(
            image.pixels, image.width, image.height,
            [{ key: "platform", color: BLACK }],
            { ...options, maxShapeSize: 50 }
        );

        expect(result.platform[0].length).toBeLessThanOrEqual(51);

    });

    it("does not join shapes around the left and right edges", () => {

        // Without an x bounds check the fill steps to x = -1, whose flat index is the
        // last pixel of the previous row, and these two blobs merge into one.
        const image = createImage(20, 20)
            .fillRect(0, 5, 4, 4, BLACK)
            .fillRect(16, 4, 4, 4, BLACK);

        const result = detectShapes(
            image.pixels, image.width, image.height,
            [{ key: "platform", color: BLACK }],
            options
        );

        expect(result.platform).toHaveLength(2);

    });

    it("gives each colour its own visited bit, so overlapping tolerances both match", () => {

        // Two targets close enough that the same grey pixels satisfy both. With a
        // single shared visited flag the second colour would find nothing.
        const grey = { r: 120, g: 120, b: 120 };
        const image = createImage(30, 20).fillRect(2, 2, 8, 8, grey);

        const result = detectShapes(
            image.pixels, image.width, image.height,
            [
                { key: "platform", color: { r: 110, g: 120, b: 120 } },
                { key: "goal", color: { r: 130, g: 120, b: 120 } }
            ],
            options
        );

        expect(result.platform).toHaveLength(1);
        expect(result.goal).toHaveLength(1);

    });

    it("refuses more than eight colours", () => {

        const image = createImage(10, 10);

        const targets = Array.from({ length: 9 }, (_, i) => ({
            key: `c${i}`,
            color: BLACK
        }));

        expect(() => detectShapes(image.pixels, 10, 10, targets, options))
            .toThrow(/at most 8 colours/);

    });

    it("finds the same shapes as the previous per-colour implementation", () => {

        const image = createImage(80, 50)
            .fillRect(1, 1, 10, 10, BLACK)
            .fillRect(30, 4, 14, 9, BLACK)
            .fillRect(60, 20, 12, 12, BLACK)
            .fillRect(5, 30, 9, 15, RED)
            .fillRect(40, 25, 20, 6, RED)
            .fillRect(20, 38, 11, 10, GREEN)
            .fillRect(66, 40, 8, 8, BLUE);

        // An L shape and a shape with a hole, to exercise the fill rather than
        // just rectangles.
        image.fillRect(45, 38, 16, 4, BLACK).fillRect(45, 42, 4, 7, BLACK);
        image.fillRect(14, 14, 12, 12, BLUE).fillRect(18, 18, 4, 4, { r: 255, g: 255, b: 255 });

        const targets = [
            { key: "platform", color: BLACK },
            { key: "goal", color: RED },
            { key: "player", color: GREEN },
            { key: "hazard", color: BLUE }
        ];

        const result = detectShapes(
            image.pixels, image.width, image.height, targets, options
        );

        targets.forEach(target => {

            const legacy = legacyGetConnectedShapes(
                image.pixels,
                image.width,
                image.height,
                (x, y, pixels, width) =>
                    matchesColor(x, y, pixels, width, target.color, options.tolerance),
                options.minShapeSize,
                options.maxShapeSize
            );

            expect(normaliseShapes(result[target.key])).toEqual(normaliseShapes(legacy));

        });

    });

});
