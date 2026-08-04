import { DETECTION, NEIGHBOURS, WEST } from "./config.js";

/**
 * Works out where the photo sits inside the world.
 *
 * The photo and the world almost never share an aspect ratio: a phone camera
 * gives 4:3, the world is 1500x800. Scaling x and y by different amounts makes
 * everything fit, but squashes the drawing, so a circle arrives as an ellipse and
 * a 45 degree ramp as a 33 degree one.
 *
 * So both axes use the same scale, the smaller of the two, and the result is
 * centred. The drawing keeps its proportions and leaves an empty bar on two
 * sides, the way a photo sits inside a frame.
 *
 * @returns {{scale: number, width: number, height: number, offsetX: number, offsetY: number}}
 */
export function fitToWorld(sourceWidth, sourceHeight, world) {

    // A source with no area would give an infinite scale, and every coordinate
    // downstream would come out NaN. Fill the world instead: wrong, but visible
    // and traceable.
    if (!(sourceWidth > 0) || !(sourceHeight > 0)) {

        return {
            scale: 1,
            width: world.width,
            height: world.height,
            offsetX: 0,
            offsetY: 0
        };

    }

    const scale = Math.min(world.width / sourceWidth, world.height / sourceHeight);

    const width = sourceWidth * scale;
    const height = sourceHeight * scale;

    return {
        scale,
        width,
        height,
        offsetX: (world.width - width) / 2,
        offsetY: (world.height - height) / 2
    };

}

/** Maps a point in photo pixels to its place in the world. */
export function toWorldPoint(point, fit) {

    return {
        x: fit.offsetX + point.x * fit.scale,
        y: fit.offsetY + point.y * fit.scale
    };

}

/**
 * Moore-neighbour tracing: walks once around a shape and returns its outer edge
 * as an ordered ring.
 *
 * The ring never crosses itself, which poly-decomp needs in order to split a
 * concave shape into convex parts.
 *
 * Only the outer edge. A shape with a hole in it is treated as solid.
 */
export function traceBoundary(shape, width) {

    const member = new Set();
    let start = shape[0];

    shape.forEach(pixel => {
        member.add(pixel.y * width + pixel.x);

        if (pixel.y < start.y || (pixel.y === start.y && pixel.x < start.x)) {
            start = pixel;
        }
    });

    // The x bounds check has to be explicit. The set is keyed on y * width + x,
    // so x = -1 gives the key of pixel (width - 1, y - 1). For a shape spanning
    // the full width, a ground platform say, that pixel is a member, and the
    // tracer walks off the left edge and reappears on the right one row up.
    // A y out of range is harmless: that key is below zero or past the end, and
    // no such key exists.
    const isInside = (x, y) => x >= 0 && x < width && member.has(y * width + x);

    const boundary = [start];

    let current = start;
    // The start is the topmost, leftmost pixel, so nothing of the shape can lie
    // above or left of it. That means we arrive from the west.
    let backtrack = WEST;
    let guard = shape.length * 4 + 16;

    while (guard-- > 0) {

        let moved = false;

        for (let step = 1; step <= 8; step++) {

            const direction = (backtrack + step) % 8;
            const [dx, dy] = NEIGHBOURS[direction];
            const x = current.x + dx;
            const y = current.y + dy;

            if (!isInside(x, y)) {
                continue;
            }

            // The direction we came from is where the next sweep starts.
            backtrack = (direction + 4) % 8;
            current = { x, y };
            moved = true;
            break;

        }

        if (!moved) {
            break; // lone pixel with no neighbours
        }

        if (current.x === start.x && current.y === start.y) {
            break; // back at the start
        }

        boundary.push(current);

    }

    return boundary;

}

export function perpendicularDistance(point, lineStart, lineEnd) {

    const dx = lineEnd.x - lineStart.x;
    const dy = lineEnd.y - lineStart.y;
    const lengthSquared = dx * dx + dy * dy;

    if (lengthSquared === 0) {
        return Math.hypot(point.x - lineStart.x, point.y - lineStart.y);
    }

    const cross =
        dy * point.x -
        dx * point.y +
        lineEnd.x * lineStart.y -
        lineEnd.y * lineStart.x;

    return Math.abs(cross) / Math.sqrt(lengthSquared);

}

/**
 * Ramer-Douglas-Peucker. Keeps corners and drops only the points that lie within
 * epsilon of the straight line between their neighbours.
 */
export function douglasPeucker(points, epsilon) {

    if (points.length < 3) {
        return points.slice();
    }

    const last = points.length - 1;
    let index = 0;
    let maxDistance = 0;

    for (let i = 1; i < last; i++) {

        const distance = perpendicularDistance(points[i], points[0], points[last]);

        if (distance > maxDistance) {
            maxDistance = distance;
            index = i;
        }

    }

    if (maxDistance <= epsilon) {
        return [points[0], points[last]];
    }

    return douglasPeucker(points.slice(0, index + 1), epsilon)
        .slice(0, -1)
        .concat(douglasPeucker(points.slice(index), epsilon));

}

/**
 * Douglas-Peucker works on an open line, so a closed ring is first split at the
 * point furthest from its start.
 */
export function simplifyRing(ring, epsilon) {

    if (ring.length < 4) {
        return ring.slice();
    }

    let farthest = 1;
    let maxDistance = 0;

    for (let i = 1; i < ring.length; i++) {

        const dx = ring[i].x - ring[0].x;
        const dy = ring[i].y - ring[0].y;
        const distance = dx * dx + dy * dy;

        if (distance > maxDistance) {
            maxDistance = distance;
            farthest = i;
        }

    }

    const firstHalf = douglasPeucker(ring.slice(0, farthest + 1), epsilon);
    const secondHalf = douglasPeucker(ring.slice(farthest).concat([ring[0]]), epsilon);

    return firstHalf.slice(0, -1).concat(secondHalf.slice(0, -1));

}

export function polygonArea(ring) {

    let sum = 0;

    for (let i = 0; i < ring.length; i++) {
        const a = ring[i];
        const b = ring[(i + 1) % ring.length];
        sum += a.x * b.y - b.x * a.y;
    }

    return Math.abs(sum) / 2;

}

export function isDegenerate(ring) {
    return ring.length < 3 || polygonArea(ring) < DETECTION.minColliderArea;
}

export function decimate(ring, maxPoints) {

    if (ring.length <= maxPoints) {
        return ring.slice();
    }

    const step = Math.ceil(ring.length / maxPoints);
    const result = [];

    for (let i = 0; i < ring.length; i += step) {
        result.push(ring[i]);
    }

    return result;

}

/**
 * Simplifies down to the vertex cap without letting the shape collapse.
 *
 * poly-decomp scales badly with vertex count, so we drop as many points as we
 * can. But a thin diagonal bar is thinner than epsilon: both long edges then read
 * as one straight line and the polygon collapses to a line with zero area. Those
 * are exactly the shapes a polygon collider is for, so in that case we refine
 * until the shape has area again.
 */
export function simplifyOutline(ring) {

    let epsilon = DETECTION.simplifyEpsilon;
    let simplified = simplifyRing(ring, epsilon);

    while (simplified.length > DETECTION.maxColliderVertices && epsilon < 64) {
        epsilon *= 1.6;
        simplified = simplifyRing(ring, epsilon);
    }

    while (isDegenerate(simplified) && epsilon > 0.2) {
        epsilon /= 2;
        simplified = simplifyRing(ring, epsilon);
    }

    if (isDegenerate(simplified)) {
        // Douglas-Peucker cannot find a fit: fall back to the raw outline, thinned.
        simplified = decimate(ring, DETECTION.maxColliderVertices);
    }

    return decimate(simplified, DETECTION.maxColliderVertices);

}

export function getBounds(points) {

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    points.forEach(point => {

        minX = Math.min(minX, point.x);
        minY = Math.min(minY, point.y);

        maxX = Math.max(maxX, point.x);
        maxY = Math.max(maxY, point.y);

    });

    const width = maxX - minX;
    const height = maxY - minY;

    return {
        minX,
        minY,
        maxX,
        maxY,
        width,
        height,
        centerX: minX + width / 2,
        centerY: minY + height / 2
    };

}
