import { describe, it, expect } from "vitest";

import { detectRoleIssues, hasAnyPixelOf, listOfRoles, roleIssueMessage } from "../roleCheck.js";
import { createImage } from "./helpers.js";

// The three roles a level cannot do without. Hazards are optional, so callers
// pass only what they require and these tests do the same.
const ROLES = [
    { key: "platform", color: "#000000", label: "Platform" },
    { key: "goal", color: "#00ff00", label: "Goal" },
    { key: "player", color: "#0000ff", label: "Player" }
];

// minShapeSize is 300, so 20x20 = 400 pixels survives detection and 10x10 = 100
// does not. That gap is the whole reason this helper exists.
const BIG = 20;
const SMALL = 10;

function imageWith(sizes) {
    const image = createImage(200, 200);

    const colors = {
        platform: { r: 0, g: 0, b: 0 },
        goal: { r: 0, g: 255, b: 0 },
        player: { r: 0, g: 0, b: 255 }
    };

    let x = 10;

    for (const [role, size] of Object.entries(sizes)) {
        if (size > 0) {
            image.fillRect(x, 10, size, size, colors[role]);
        }

        x += 60;
    }

    return image;
}

describe("detectRoleIssues", () => {

    it("finds no problem when every role is drawn big enough", () => {
        const image = imageWith({ platform: BIG, goal: BIG, player: BIG });

        const issues = detectRoleIssues(image.pixels, image.width, image.height, ROLES);

        expect(issues.missing).toEqual([]);
        expect(issues.tooSmall).toEqual([]);
    });

    it("reports a role that was never drawn as missing", () => {
        const image = imageWith({ platform: BIG, goal: BIG, player: 0 });

        const issues = detectRoleIssues(image.pixels, image.width, image.height, ROLES);

        expect(issues.missing.map((role) => role.key)).toEqual(["player"]);
        expect(issues.tooSmall).toEqual([]);
    });

    // The distinction the helper exists for: a naive "is this colour present?"
    // check passes here and still yields a game with no player in it.
    it("reports a role drawn below the minimum shape size as too small", () => {
        const image = imageWith({ platform: BIG, goal: BIG, player: SMALL });

        const issues = detectRoleIssues(image.pixels, image.width, image.height, ROLES);

        expect(issues.missing).toEqual([]);
        expect(issues.tooSmall.map((role) => role.key)).toEqual(["player"]);
    });

    it("reports every role that is absent, not just the first", () => {
        const image = imageWith({ platform: BIG, goal: 0, player: 0 });

        const issues = detectRoleIssues(image.pixels, image.width, image.height, ROLES);

        expect(issues.missing.map((role) => role.key)).toEqual(["goal", "player"]);
    });

});

describe("hasAnyPixelOf", () => {

    it("finds a colour that is present", () => {
        const image = imageWith({ platform: SMALL });

        expect(hasAnyPixelOf(image.pixels, "#000000")).toBe(true);
    });

    it("does not find a colour that is absent", () => {
        const image = imageWith({ platform: BIG });

        expect(hasAnyPixelOf(image.pixels, "#0000ff")).toBe(false);
    });

});

describe("listOfRoles", () => {

    it("names one role", () => {
        expect(listOfRoles([{ label: "Player" }])).toBe("a player");
    });

    it("joins the last two with and", () => {
        expect(listOfRoles([{ label: "Platform" }, { label: "Goal" }, { label: "Player" }]))
            .toBe("a platform, a goal and a player");
    });

});

describe("roleIssueMessage", () => {

    it("says nothing when the level will parse", () => {
        expect(roleIssueMessage({ missing: [], tooSmall: [] })).toBe("");
    });

    it("asks for what was never drawn", () => {
        expect(roleIssueMessage({ missing: [{ label: "Goal" }], tooSmall: [] }))
            .toBe("Your level still needs a goal.");
    });

    // Never drawn and drawn-too-small need different advice, and the first is
    // the more basic problem, so it is the one reported.
    it("prefers the missing message when both apply", () => {
        const message = roleIssueMessage({
            missing: [{ label: "Goal" }],
            tooSmall: [{ label: "Player" }]
        });

        expect(message).toBe("Your level still needs a goal.");
    });

    it("tells you to make a too-small mark bigger", () => {
        expect(roleIssueMessage({ missing: [], tooSmall: [{ label: "Player" }] }))
            .toContain("too small for the game to see");
    });

});
