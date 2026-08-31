import { detectShapes, hexToRgb } from "./colorDetection.js";
import { DETECTION } from "./config.js";

const PAPER = "#ffffff";

/**
 * @param {Array<{key: string, color: string, label: string}>} roles
 * @returns {Array<Array>} the pairs that clash; a role paired with null is one
 */
export function colorsTooClose(roles) {
    const clashes = [];

    for (let i = 0; i < roles.length; i++) {
        if (! farEnough(roles[i].color, PAPER)) {
            clashes.push([roles[i], null]);
        }

        for (let j = i + 1; j < roles.length; j++) {
            if (! farEnough(roles[i].color, roles[j].color)) {
                clashes.push([roles[i], roles[j]]);
            }
        }
    }

    return clashes;
}

function farEnough(a, b) {
    const first = hexToRgb(a);
    const second = hexToRgb(b);

    const dr = first.r - second.r;
    const dg = first.g - second.g;
    const db = first.b - second.b;

    return dr * dr + dg * dg + db * db >= DETECTION.colorTolerance * DETECTION.colorTolerance;
}

export function colorClashMessage(clashes) {
    if (clashes.length === 0) {
        return "";
    }

    const [first, second] = clashes[0];

    if (second === null) {
        return `Your ${first.label.toLowerCase()} colour is too close to the paper — the game would read the page itself as part of the level. Pick something bolder.`;
    }

    return `Your ${first.label.toLowerCase()} and ${second.label.toLowerCase()} colours are too alike for the game to tell apart. Move them further apart.`;
}

/**
 * @param {Uint8ClampedArray} data RGBA pixels, as getImageData() returns
 * @param {number} width
 * @param {number} height
 * @param {Array<{key: string, color: string, label: string}>} roles
 * @returns {{missing: Array, tooSmall: Array}}
 */
export function detectRoleIssues(data, width, height, roles) {
    const detected = detectShapes(
        data,
        width,
        height,
        roles.map((role) => ({ key: role.key, color: hexToRgb(role.color) }))
    );

    const missing = [];
    const tooSmall = [];

    for (const role of roles) {
        if (detected[role.key].length > 0) {
            continue;
        }

        (hasAnyPixelOf(data, role.color) ? tooSmall : missing).push(role);
    }

    return { missing, tooSmall };
}

export function hasAnyPixelOf(data, hex) {
    const value = parseInt(hex.slice(1), 16);

    for (let i = 0; i < data.length; i += 4) {
        if (((data[i] << 16) | (data[i + 1] << 8) | data[i + 2]) === value) {
            return true;
        }
    }

    return false;
}

export function listOfRoles(roles) {
    const names = roles.map((role) => `a ${role.label.toLowerCase()}`);

    return names.length > 1
        ? `${names.slice(0, -1).join(", ")} and ${names[names.length - 1]}`
        : names[0];
}

export function roleIssueMessage({ missing, tooSmall }) {
    if (missing.length > 0) {
        return `Your level still needs ${listOfRoles(missing)}.`;
    }

    if (tooSmall.length > 0) {
        return `You drew ${listOfRoles(tooSmall)}, but too small for the game to see — make ${tooSmall.length > 1 ? "them" : "it"} bigger.`;
    }

    return "";
}
