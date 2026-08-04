import { describe, it, expect } from "vitest";

import {
    traceBoundary,
    perpendicularDistance,
    douglasPeucker,
    simplifyOutline,
    polygonArea,
    decimate,
    getBounds,
    fitToWorld,
    toWorldPoint
} from "../shapeTracing.js";

import { rectPixels } from "./helpers.js";

const WIDTH = 60;

/**
 * Every consecutive pair in the ring has to be 8-connected, including the closing
 * step. That proves the outline is one continuous walk and does not jump between
 * separate edges.
 */
function isClosedWalk(ring) {

    for (let i = 0; i < ring.length; i++) {
        const a = ring[i];
        const b = ring[(i + 1) % ring.length];

        if (Math.abs(a.x - b.x) > 1 || Math.abs(a.y - b.y) > 1) {
            return false;
        }
    }

    return true;

}

/** A bar 4 px thick running diagonally down and to the right. */
function diagonalBar() {

    const shape = [];

    for (let i = 0; i < 25; i++) {
        for (let t = 0; t < 4; t++) {
            shape.push({ x: 5 + i, y: 5 + i + t });
        }
    }

    return shape;

}

describe("traceBoundary", () => {

    it("walks around a rectangle with the right perimeter", () => {

        const ring = traceBoundary(rectPixels(5, 5, 20, 10), WIDTH);

        expect(isClosedWalk(ring)).toBe(true);
        expect(ring).toHaveLength(2 * (20 + 10) - 4);

    });

    it("uses every pixel at most once", () => {

        const ring = traceBoundary(rectPixels(5, 5, 20, 10), WIDTH);
        const unique = new Set(ring.map(p => `${p.x},${p.y}`));

        expect(unique.size).toBe(ring.length);

    });

    it("stays inside the shape", () => {

        const shape = rectPixels(5, 5, 20, 10);
        const members = new Set(shape.map(p => `${p.x},${p.y}`));
        const ring = traceBoundary(shape, WIDTH);

        expect(ring.every(p => members.has(`${p.x},${p.y}`))).toBe(true);

    });

    it("follows a concave L shape without jumping the gap", () => {

        const shape = [...rectPixels(5, 5, 20, 6), ...rectPixels(5, 11, 8, 12)];
        const ring = traceBoundary(shape, WIDTH);

        expect(isClosedWalk(ring)).toBe(true);
        expect(new Set(ring.map(p => `${p.x},${p.y}`)).size).toBe(ring.length);

    });

    it("does not wrap around the edge for a full width shape", () => {

        // A ground platform spans the full width. The set is keyed on y * width + x,
        // so x = -1 gives the key of (width - 1, y - 1), which is part of this shape.
        // Without a bounds check the tracer walks off the left edge, reappears on the
        // right one row up, and circles until the guard stops it.
        const width = 40;
        const ring = traceBoundary(rectPixels(0, 10, width, 6), width);

        expect(ring).toHaveLength(2 * (width + 6) - 4);
        expect(isClosedWalk(ring)).toBe(true);
        expect(simplifyOutline(ring)).toHaveLength(4);

    });

    it("does not wrap around the edge for a shape against the left", () => {

        const ring = traceBoundary(rectPixels(0, 5, 12, 8), 40);

        expect(ring).toHaveLength(2 * (12 + 8) - 4);
        expect(isClosedWalk(ring)).toBe(true);

    });

    it("does not wrap around the edge for a shape against the right", () => {

        const ring = traceBoundary(rectPixels(28, 5, 12, 8), 40);

        expect(ring).toHaveLength(2 * (12 + 8) - 4);
        expect(isClosedWalk(ring)).toBe(true);

    });

    it("does not hang on a lone pixel", () => {
        expect(traceBoundary([{ x: 10, y: 10 }], WIDTH)).toHaveLength(1);
    });

    it("does not hang on a line one pixel high", () => {

        const ring = traceBoundary(rectPixels(5, 5, 12, 1), WIDTH);

        expect(ring.length).toBeGreaterThan(0);
        expect(ring.length).toBeLessThanOrEqual(24);

    });

});

describe("perpendicularDistance", () => {

    it("measures the distance to a horizontal line", () => {

        expect(perpendicularDistance(
            { x: 5, y: 3 }, { x: 0, y: 0 }, { x: 10, y: 0 }
        )).toBeCloseTo(3);

    });

    it("falls back to point distance when the line has no length", () => {

        expect(perpendicularDistance(
            { x: 3, y: 4 }, { x: 0, y: 0 }, { x: 0, y: 0 }
        )).toBeCloseTo(5);

    });

});

describe("douglasPeucker", () => {

    it("drops points that sit on the straight line", () => {

        const points = [
            { x: 0, y: 0 },
            { x: 2, y: 0 },
            { x: 5, y: 0 },
            { x: 8, y: 0 },
            { x: 10, y: 0 }
        ];

        expect(douglasPeucker(points, 1)).toEqual([{ x: 0, y: 0 }, { x: 10, y: 0 }]);

    });

    it("keeps a corner that sticks out further than epsilon", () => {

        const points = [
            { x: 0, y: 0 },
            { x: 5, y: 6 },
            { x: 10, y: 0 }
        ];

        expect(douglasPeucker(points, 1)).toHaveLength(3);

    });

});

describe("simplifyOutline", () => {

    it("reduces a rectangle to four corners", () => {

        const ring = traceBoundary(rectPixels(5, 5, 20, 10), WIDTH);

        expect(simplifyOutline(ring)).toHaveLength(4);

    });

    it("reduces an L shape to six corners", () => {

        const shape = [...rectPixels(5, 5, 20, 6), ...rectPixels(5, 11, 8, 12)];

        expect(simplifyOutline(traceBoundary(shape, WIDTH))).toHaveLength(6);

    });

    it("does not let a thin diagonal bar collapse into a line", () => {

        // The thickness across the bar (about 2.8 px) is smaller than the default
        // epsilon of 2.5. Without a guard Douglas-Peucker reads both long edges as
        // one straight line and leaves a line with zero area.
        const simplified = simplifyOutline(traceBoundary(diagonalBar(), WIDTH));

        expect(simplified.length).toBeGreaterThanOrEqual(3);
        expect(polygonArea(simplified)).toBeGreaterThan(8);

    });

    it("does not let a thin horizontal platform collapse", () => {

        const simplified = simplifyOutline(traceBoundary(rectPixels(5, 5, 40, 3), WIDTH));

        expect(simplified.length).toBeGreaterThanOrEqual(3);
        expect(polygonArea(simplified)).toBeGreaterThan(8);

    });

    it("covers far less area than the bounding box for a diagonal bar", () => {

        // The gain from polygon colliders in one number: the difference is the area
        // where the player would otherwise hit an invisible wall.
        const simplified = simplifyOutline(traceBoundary(diagonalBar(), WIDTH));
        const bounds = getBounds(simplified);

        expect(polygonArea(simplified) / (bounds.width * bounds.height))
            .toBeLessThan(0.4);

    });

    it("stays under the vertex cap", () => {

        // A circle gives an outline with hundreds of points.
        const shape = [];
        const radius = 40;

        for (let y = -radius; y <= radius; y++) {
            for (let x = -radius; x <= radius; x++) {
                if (x * x + y * y <= radius * radius) {
                    shape.push({ x: x + 50, y: y + 50 });
                }
            }
        }

        expect(simplifyOutline(traceBoundary(shape, 200)).length)
            .toBeLessThanOrEqual(64);

    });

});

describe("polygonArea", () => {

    it("works out the area of a square", () => {

        expect(polygonArea([
            { x: 0, y: 0 },
            { x: 10, y: 0 },
            { x: 10, y: 10 },
            { x: 0, y: 10 }
        ])).toBe(100);

    });

    it("returns zero for a line segment", () => {

        expect(polygonArea([{ x: 0, y: 0 }, { x: 10, y: 10 }])).toBe(0);

    });

});

describe("decimate", () => {

    it("leaves a ring under the cap alone", () => {

        const ring = rectPixels(0, 0, 1, 5);

        expect(decimate(ring, 10)).toHaveLength(5);

    });

    it("thins a ring down to at most the cap", () => {

        const ring = rectPixels(0, 0, 1, 100);

        expect(decimate(ring, 10).length).toBeLessThanOrEqual(10);

    });

});

describe("getBounds", () => {

    it("measures a rectangle", () => {

        expect(getBounds([
            { x: 10, y: 20 },
            { x: 30, y: 60 },
            { x: 20, y: 40 }
        ])).toMatchObject({
            minX: 10,
            minY: 20,
            maxX: 30,
            maxY: 60,
            width: 20,
            height: 40,
            centerX: 20,
            centerY: 40
        });

    });

    it("gives a single point zero width and height", () => {

        const bounds = getBounds([{ x: 5, y: 5 }]);

        expect(bounds.width).toBe(0);
        expect(bounds.height).toBe(0);
        expect(bounds.centerX).toBe(5);
        expect(bounds.centerY).toBe(5);

    });

});

describe("fitToWorld", () => {

    const world = { width: 1500, height: 800 };

    it("letterboxes a photo that is taller than the world", () => {

        // 4:3, the shape a phone camera gives. Height runs out first.
        const fit = fitToWorld(400, 300, world);

        expect(fit.scale).toBeCloseTo(800 / 300);
        expect(fit.height).toBeCloseTo(800);
        expect(fit.width).toBeCloseTo(400 * (800 / 300));
        expect(fit.offsetY).toBeCloseTo(0);
        expect(fit.offsetX).toBeCloseTo((1500 - 400 * (800 / 300)) / 2);

    });

    it("pillarboxes a photo that is wider than the world", () => {

        const fit = fitToWorld(3000, 1000, world);

        expect(fit.scale).toBeCloseTo(0.5);
        expect(fit.width).toBeCloseTo(1500);
        expect(fit.height).toBeCloseTo(500);
        expect(fit.offsetX).toBeCloseTo(0);
        expect(fit.offsetY).toBeCloseTo(150);

    });

    it("keeps the fitted drawing inside the world", () => {

        [[400, 300], [3000, 1000], [1500, 800], [100, 4000]].forEach(([w, h]) => {

            const fit = fitToWorld(w, h, world);

            expect(fit.offsetX).toBeGreaterThanOrEqual(0);
            expect(fit.offsetY).toBeGreaterThanOrEqual(0);
            expect(fit.offsetX + fit.width).toBeLessThanOrEqual(world.width + 1e-9);
            expect(fit.offsetY + fit.height).toBeLessThanOrEqual(world.height + 1e-9);

        });

    });

    it("scales up a photo smaller than the world", () => {

        const fit = fitToWorld(150, 80, world);

        expect(fit.scale).toBeCloseTo(10);

    });

    it("fills the world rather than returning NaN for an empty photo", () => {

        expect(fitToWorld(0, 0, world)).toMatchObject({
            scale: 1,
            width: 1500,
            height: 800,
            offsetX: 0,
            offsetY: 0
        });

    });

    /**
     * The reason the step exists: a square drawn on the paper has to stay square.
     * Scaling x and y separately turned it into a 1500x800-shaped rectangle.
     */
    it("keeps a square square", () => {

        const fit = fitToWorld(400, 300, world);

        const corners = [
            { x: 100, y: 100 },
            { x: 200, y: 100 },
            { x: 200, y: 200 },
            { x: 100, y: 200 }
        ].map(point => toWorldPoint(point, fit));

        const bounds = getBounds(corners);

        expect(bounds.width).toBeCloseTo(bounds.height);

    });

    it("keeps the angle of a slope", () => {

        const fit = fitToWorld(400, 300, world);

        const start = toWorldPoint({ x: 0, y: 0 }, fit);
        const end = toWorldPoint({ x: 100, y: 100 }, fit);

        expect(Math.atan2(end.y - start.y, end.x - start.x)).toBeCloseTo(Math.PI / 4);

    });

});

describe("toWorldPoint", () => {

    it("puts the top left of the photo at the top left of the fitted area", () => {

        const fit = fitToWorld(400, 300, { width: 1500, height: 800 });

        expect(toWorldPoint({ x: 0, y: 0 }, fit)).toMatchObject({
            x: fit.offsetX,
            y: fit.offsetY
        });

    });

    it("puts the bottom right of the photo at the bottom right of the fitted area", () => {

        const fit = fitToWorld(400, 300, { width: 1500, height: 800 });
        const corner = toWorldPoint({ x: 400, y: 300 }, fit);

        expect(corner.x).toBeCloseTo(fit.offsetX + fit.width);
        expect(corner.y).toBeCloseTo(fit.offsetY + fit.height);

    });

});
