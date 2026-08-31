import { DETECTION, NEIGHBOURS, WEST } from "./config.js";

/**
 * @returns {{scale: number, width: number, height: number, offsetX: number, offsetY: number}}
 */
export function fitToWorld(sourceWidth, sourceHeight, world) {
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

export function toWorldPoint(point, fit) {
    return {
        x: fit.offsetX + point.x * fit.scale,
        y: fit.offsetY + point.y * fit.scale
    };

}

export function traceBoundary(shape, width) {
    const member = new Set();
    let start = shape[0];

    shape.forEach(pixel => {
        member.add(pixel.y * width + pixel.x);

        if (pixel.y < start.y || (pixel.y === start.y && pixel.x < start.x)) {
            start = pixel;
        }
    });

    const isInside = (x, y) => x >= 0 && x < width && member.has(y * width + x);

    const boundary = [start];

    let current = start;
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

            backtrack = (direction + 4) % 8;
            current = { x, y };
            moved = true;
            break;

        }

        if (!moved) {
            break;
        }

        if (current.x === start.x && current.y === start.y) {
            break;
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
