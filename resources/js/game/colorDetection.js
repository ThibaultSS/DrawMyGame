import { DETECTION } from "./config.js";

export function hexToRgb(hex) {
    hex = String(hex).replace("#", "");

    return {
        r: parseInt(hex.substring(0, 2), 16),
        g: parseInt(hex.substring(2, 4), 16),
        b: parseInt(hex.substring(4, 6), 16)
    };

}

export function matchesColor(
    x,
    y,
    pixels,
    width,
    targetColor,
    tolerance = DETECTION.colorTolerance
) {
    const index = (y * width + x) * 4;

    const dr = pixels[index]     - targetColor.r;
    const dg = pixels[index + 1] - targetColor.g;
    const db = pixels[index + 2] - targetColor.b;

    return dr * dr + dg * dg + db * db < tolerance * tolerance;

}

/**
 * @param {Uint8ClampedArray} pixels RGBA data from getImageData()
 * @param {Array<{key: string, color: {r: number, g: number, b: number}}>} targets at most 8 colours
 * @returns {Object<string, Array<Array<{x: number, y: number}>>>} shapes per colour key
 */
export function detectShapes(pixels, width, height, targets, options = {}) {
    if (targets.length > 8) {
        throw new Error("detectShapes supports at most 8 colours: one bit per colour.");
    }

    const tolerance = options.tolerance ?? DETECTION.colorTolerance;
    const minShapeSize = options.minShapeSize ?? DETECTION.minShapeSize;
    const maxShapeSize = options.maxShapeSize ?? DETECTION.maxShapeSize;

    const visited = new Uint8Array(width * height);
    const result = {};

    targets.forEach(target => {
        result[target.key] = [];
    });

    function floodFill(startX, startY, color, bit) {
        const stack = [startX, startY];
        const shape = [];

        while (stack.length > 0) {
            const y = stack.pop();
            const x = stack.pop();
            const index = y * width + x;

            if (visited[index] & bit) {
                continue;
            }

            visited[index] |= bit;

            if (!matchesColor(x, y, pixels, width, color, tolerance)) {
                continue;
            }

            shape.push({ x, y });

            if (shape.length > maxShapeSize) {
                return shape;
            }

            if (x + 1 < width)  { stack.push(x + 1, y); }
            if (x - 1 >= 0)     { stack.push(x - 1, y); }
            if (y + 1 < height) { stack.push(x, y + 1); }
            if (y - 1 >= 0)     { stack.push(x, y - 1); }

        }

        return shape;

    }

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const index = y * width + x;

            for (let t = 0; t < targets.length; t++) {
                const bit = 1 << t;

                if (visited[index] & bit) {
                    continue;
                }

                if (!matchesColor(x, y, pixels, width, targets[t].color, tolerance)) {
                    continue;
                }

                const shape = floodFill(x, y, targets[t].color, bit);

                if (shape.length > minShapeSize) {
                    result[targets[t].key].push(shape);
                }

            }

        }
    }

    return result;

}
