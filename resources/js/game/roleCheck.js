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
