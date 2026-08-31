export const BLACK = { r: 0, g: 0, b: 0 };
export const RED = { r: 255, g: 0, b: 0 };
export const GREEN = { r: 0, g: 255, b: 0 };
export const BLUE = { r: 0, g: 0, b: 255 };

export function createImage(width, height) {
    const pixels = new Uint8ClampedArray(width * height * 4);

    pixels.fill(255);

    return {
        width,
        height,
        pixels,

        fillRect(x0, y0, w, h, color) {
            for (let y = y0; y < y0 + h; y++) {
                for (let x = x0; x < x0 + w; x++) {
                    const index = (y * width + x) * 4;
                    pixels[index] = color.r;
                    pixels[index + 1] = color.g;
                    pixels[index + 2] = color.b;
                    pixels[index + 3] = 255;
                }
            }
            return this;
        },

        setPixel(x, y, color) {
            const index = (y * width + x) * 4;
            pixels[index] = color.r;
            pixels[index + 1] = color.g;
            pixels[index + 2] = color.b;
            pixels[index + 3] = 255;
            return this;
        }
    };

}

export function rectPixels(x0, y0, w, h) {
    const pixels = [];

    for (let y = y0; y < y0 + h; y++) {
        for (let x = x0; x < x0 + w; x++) {
            pixels.push({ x, y });
        }
    }

    return pixels;

}

export function legacyGetConnectedShapes(pixels, width, height, colorCheck, minShapeSize, maxShapeSize) {
    const visited = new Set();
    const shapes = [];

    function floodFill(startX, startY) {
        const stack = [[startX, startY]];
        const shape = [];

        while (stack.length > 0) {
            const [x, y] = stack.pop();
            const key = `${x},${y}`;

            if (visited.has(key)) continue;
            visited.add(key);
            if (!colorCheck(x, y, pixels, width)) continue;

            shape.push({ x, y });

            if (shape.length > maxShapeSize) {
                return shape;
            }

            if (x + 1 < width)  stack.push([x + 1, y]);
            if (x - 1 >= 0)     stack.push([x - 1, y]);
            if (y + 1 < height) stack.push([x, y + 1]);
            if (y - 1 >= 0)     stack.push([x, y - 1]);

        }

        return shape;

    }

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (visited.has(`${x},${y}`)) continue;

            if (colorCheck(x, y, pixels, width)) {
                const shape = floodFill(x, y);
                if (shape.length > minShapeSize) {
                    shapes.push(shape);
                }
            }

        }
    }

    return shapes;

}

export function normaliseShapes(shapes) {
    return shapes
        .map(shape =>
            shape
                .map(p => `${p.x},${p.y}`)
                .sort()
                .join(" ")
        )
        .sort();

}
