/**
 * Will this picture actually parse into a level?
 *
 * Answered by running the game's own detector, not by looking for coloured
 * pixels. The distinction is the whole point: the detector drops shapes below
 * its minimum size, so a lone small dot of player-blue passes a naive pixel
 * check and still produces a game with no player in it.
 *
 * Both ways in use this. The drawing page checks its canvas before posting, and
 * the colour-picking page checks the photo before starting — that second one is
 * where the failure used to be silent, because a photo whose colours did not
 * resolve simply produced a broken level with no explanation.
 */
import { detectShapes, hexToRgb } from "./colorDetection.js";
import { DETECTION } from "./config.js";

/** The paper the drawing page paints on, and what the eraser paints with. */
const PAPER = "#ffffff";

/**
 * The colours a level is drawn in have to be far enough apart for the detector
 * to tell them apart — and far enough from the paper not to detect the page.
 *
 * The detector matches within a Euclidean RGB distance of DETECTION.
 * colorTolerance, so two colours closer than that overlap: a pixel can satisfy
 * both, and a platform would also count as a hazard. This is the same number,
 * read from the same place, rather than a second copy that could drift.
 *
 * @param {Array<{key: string, color: string, label: string}>} roles
 * @returns {Array<Array>} the pairs that clash; a role paired with null is one
 *   too close to the paper
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

/**
 * Squared distance against squared tolerance, the same comparison the detector
 * makes per pixel — so "far enough" here means exactly what it means there.
 */
function farEnough(a, b) {
    const first = hexToRgb(a);
    const second = hexToRgb(b);

    const dr = first.r - second.r;
    const dg = first.g - second.g;
    const db = first.b - second.b;

    return dr * dr + dg * dg + db * db >= DETECTION.colorTolerance * DETECTION.colorTolerance;
}

/** The one sentence to show when colours clash, or an empty string. */
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
 * Which required roles the engine would fail to find, and why.
 *
 * The two lists mean different things and need different advice: `missing` was
 * never drawn at all, `tooSmall` was drawn but not big enough to survive
 * detection.
 *
 * @param {Uint8ClampedArray} data RGBA pixels, as getImageData() returns
 * @param {number} width
 * @param {number} height
 * @param {Array<{key: string, color: string, label: string}>} roles
 * @returns {{missing: Array, tooSmall: Array}}
 */
export function detectRoleIssues(data, width, height, roles) {
    // Hazards are optional — a level without danger is still playable — so a
    // caller passes only the roles it requires.
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

/**
 * Whether the colour appears anywhere at all, exactly.
 *
 * Exact rather than within the detector's tolerance on purpose: this only has
 * to tell "you drew none of this" from "you drew some of this", and the drawing
 * page paints in precisely these values.
 */
export function hasAnyPixelOf(data, hex) {
    const value = parseInt(hex.slice(1), 16);

    for (let i = 0; i < data.length; i += 4) {
        if (((data[i] << 16) | (data[i + 1] << 8) | data[i + 2]) === value) {
            return true;
        }
    }

    return false;
}

/** "a platform, a goal and a player" — for dropping into a sentence. */
export function listOfRoles(roles) {
    const names = roles.map((role) => `a ${role.label.toLowerCase()}`);

    return names.length > 1
        ? `${names.slice(0, -1).join(", ")} and ${names[names.length - 1]}`
        : names[0];
}

/**
 * The one sentence to show, or an empty string when the level will parse.
 *
 * Kept here rather than in either page so both say the same thing.
 */
export function roleIssueMessage({ missing, tooSmall }) {
    if (missing.length > 0) {
        return `Your level still needs ${listOfRoles(missing)}.`;
    }

    if (tooSmall.length > 0) {
        return `You drew ${listOfRoles(tooSmall)}, but too small for the game to see — make ${tooSmall.length > 1 ? "them" : "it"} bigger.`;
    }

    return "";
}
