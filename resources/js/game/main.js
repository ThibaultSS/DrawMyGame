import Phaser from "phaser";

import { WORLD } from "./config.js";
import { hexToRgb, detectShapes } from "./colorDetection.js";
import {
    traceBoundary,
    simplifyOutline,
    getBounds,
    fitToWorld,
    toWorldPoint
} from "./shapeTracing.js";

const Matter = Phaser.Physics.Matter.Matter;

// Matter can only split a concave shape into convex parts when poly-decomp is
// registered. Phaser 4 bundles poly-decomp and registers it when its Matter plugin
// loads, so there is nothing to install. If that ever stops being true,
// fromVertices quietly returns the convex hull instead and concave platforms gain
// solid notches, so say so rather than fail silently.
if (typeof Matter.Common.getDecomp === "function" && !Matter.Common.getDecomp()) {
    console.warn("poly-decomp is not registered with Matter: concave shapes will collide as their convex hull.");
}

const config = {
    type: Phaser.AUTO,

    width: WORLD.width,
    height: WORLD.height,

    parent: "game-container",
    transparent: true,

    physics: {
        default: "matter",
        matter: {
            gravity: {
                y: 1
            },

            debug: false
        }
    },

    scene: {
        preload,
        create,
        update
    }
};

let player;
let cursors;
let canJump = false;

let moveSpeed = 5;
let jumpStrength = 10;

let outlines = [];
let goalOutlines = [];
let hazardOutlines = [];
let playerOutline = null;
let playerGraphics;

// Centre of the drawn player shape. Never changes, so work it out once.
let playerOrigin = null;

// Where the photo ended up inside the world once it was fitted. The area outside
// it is not part of the drawing, so it is not part of the level either.
let levelRect = null;

function preload() {
    this.load.image("levelImage", window.levelImage);
}

/* ------------------------------------------------------------------ *
 * From photo to outlines
 * ------------------------------------------------------------------ */

function imageToLevelData(scene) {

    const source = scene.textures.get("levelImage").getSourceImage();

    // One scale for both axes, so the drawing is not squashed to fit.
    levelRect = fitToWorld(source.width, source.height, WORLD);

    const canvas = document.createElement("canvas");
    canvas.width = source.width;
    canvas.height = source.height;

    const ctx = canvas.getContext("2d");
    ctx.drawImage(source, 0, 0);

    const pixels = ctx.getImageData(0, 0, source.width, source.height).data;

    const detected = detectShapes(
        pixels,
        source.width,
        source.height,
        [
            { key: "platform", color: hexToRgb(window.platformColor) },
            { key: "goal",     color: hexToRgb(window.goalColor) },
            { key: "player",   color: hexToRgb(window.playerColor) },
            { key: "hazard",   color: hexToRgb(window.hazardColor) }
        ]
    );

    // One outline per shape, scaled to world coordinates. The same points are used
    // both to draw the shape and to build its collider, so the two always match.
    const toWorldOutline = shape =>
        simplifyOutline(traceBoundary(shape, source.width))
            .map(point => toWorldPoint(point, levelRect));

    outlines = detected.platform.map(toWorldOutline);
    goalOutlines = detected.goal.map(toWorldOutline);
    hazardOutlines = detected.hazard.map(toWorldOutline);

    // Only the largest shape in the player colour counts, so stray marks in that
    // colour do not become a second player.
    if (detected.player.length > 0) {
        const biggest = detected.player.sort((a, b) => b.length - a.length)[0];
        playerOutline = toWorldOutline(biggest);
    }

}

/* ------------------------------------------------------------------ *
 * Drawing and colliders
 * ------------------------------------------------------------------ */

function createObjects(scene, shapes, color) {

    const graphics = scene.add.graphics();

    graphics.fillStyle(color);

    shapes.forEach(shape => {

        if (!shape || shape.length < 3) {
            return;
        }

        graphics.beginPath();
        graphics.moveTo(shape[0].x, shape[0].y);

        shape.forEach(point => {
            graphics.lineTo(point.x, point.y);
        });

        graphics.closePath();
        graphics.fillPath();

    });

}

/**
 * Builds a real polygon collider per outline, so the player collides with exactly
 * the line that is drawn on screen.
 *
 * If the decomposition fails, falls back to the bounding box. A slightly too
 * generous collider beats a level with no floor.
 */
function createShapeBodies(scene, shapeOutlines, options = {}, label = null) {

    shapeOutlines.forEach(outline => {

        const body =
            createPolygonBody(scene, outline, options) ??
            createBoundingBoxBody(scene, outline, options);

        if (body && label) {
            // poly-decomp splits a concave shape into a compound body, and Matter
            // reports collisions on the parts rather than the parent. The label has
            // to sit on every part or the collision code will not see goal and
            // hazard.
            body.parts.forEach(part => {
                part.label = label;
            });
        }

    });

}

function createPolygonBody(scene, outline, options) {

    if (!outline || outline.length < 3) {
        return null;
    }

    const vertices = outline.map(point => ({ x: point.x, y: point.y }));

    // fromVertices puts the body's centre of mass at (x, y), and Vertices.centre
    // gives exactly that point, so the collider lines up with the drawing.
    const centre = Matter.Vertices.centre(vertices);

    if (!Number.isFinite(centre.x) || !Number.isFinite(centre.y)) {
        return null;
    }

    try {

        const body = scene.matter.add.fromVertices(
            centre.x,
            centre.y,
            [vertices],
            options,
            true,  // flag internal edges so the player does not snag on seams
            0.01,  // tolerance for dropping collinear points
            20     // minimum area of a part
        );

        return body && body.parts && body.parts.length > 0 ? body : null;

    } catch (error) {

        console.warn("Polygon collider failed, falling back to bounding box", error);
        return null;

    }

}

function createBoundingBoxBody(scene, outline, options) {

    if (!outline || outline.length === 0) {
        return null;
    }

    const bounds = getBounds(outline);

    if (bounds.width <= 0 || bounds.height <= 0) {
        return null;
    }

    return scene.matter.add.rectangle(
        bounds.centerX,
        bounds.centerY,
        bounds.width,
        bounds.height,
        options
    );

}

function drawPlayer(offsetX = 0, offsetY = 0) {

    playerGraphics.clear();
    playerGraphics.fillStyle(parseInt(window.playerColor.replace("#", "0x")));

    playerGraphics.beginPath();
    playerGraphics.moveTo(
        playerOutline[0].x + offsetX,
        playerOutline[0].y + offsetY
    );

    playerOutline.forEach(point => {
        playerGraphics.lineTo(point.x + offsetX, point.y + offsetY);
    });

    playerGraphics.closePath();
    playerGraphics.fillPath();

}

function createPlayer(scene) {

    if (!playerOutline) {
        return;
    }

    playerGraphics = scene.add.graphics();
    playerGraphics.setDepth(1000);

    drawPlayer();

    const bounds = getBounds(playerOutline);

    playerOrigin = {
        x: bounds.centerX,
        y: bounds.centerY
    };

    player = scene.matter.add.rectangle(
        bounds.centerX,
        bounds.centerY,
        bounds.width,
        bounds.height,
        {
            isStatic: false
        }
    );

    // Stops the player from tipping over.
    Matter.Body.setInertia(player, Infinity);

}

function showPopup(message) {

    window.gamePaused = true;

    document.getElementById("popup-message").textContent = message;
    document.getElementById("popup").style.display = "flex";

    if (message === "You won!" && typeof confetti === "function") {

        confetti({ particleCount: 200, spread: 120, origin: { y: 0.4 } });

        setTimeout(() => {
            confetti({ particleCount: 150, angle: 60, spread: 80, origin: { x: 0, y: 0.5 } });
        }, 200);

        setTimeout(() => {
            confetti({ particleCount: 150, angle: 120, spread: 80, origin: { x: 1, y: 0.5 } });
        }, 400);

        setTimeout(() => {
            confetti({ particleCount: 300, spread: 160, origin: { y: 0.3 } });
        }, 600);

    }

}

/* ------------------------------------------------------------------ *
 * Scene
 * ------------------------------------------------------------------ */

function create() {

    cursors = this.input.keyboard.createCursorKeys();
    this.input.mouse.disableContextMenu();

    imageToLevelData(this);

    // The level is the drawing, not the canvas. Fitting the photo leaves an empty
    // bar on two sides, and the walls go around the drawing so the player cannot
    // walk out into it.
    this.matter.world.setBounds(
        levelRect.offsetX,
        levelRect.offsetY,
        levelRect.width,
        levelRect.height
    );

    createPlayer(this);

    this.matter.world.on("collisionstart", (event) => {

        event.pairs.forEach(pair => {

            const bodyA = pair.bodyA;
            const bodyB = pair.bodyB;

            // Matter reports the part of a compound body, so compare against the
            // parent. A simple body is its own parent, so this works either way.
            const playerIsA = (bodyA.parent || bodyA) === player;
            const playerIsB = (bodyB.parent || bodyB) === player;

            if (!playerIsA && !playerIsB) {
                return;
            }

            // Any contact refills the jump: there is no ground check yet.
            canJump = true;

            const other = playerIsA ? bodyB : bodyA;

            if (other.label === "goal") {
                showPopup("You won!");
            }

            if (other.label === "hazard") {
                showPopup("You lost!");
            }

        });

    });

    // Platforms
    createObjects(this, outlines, window.platformColor.replace("#", "0x"));
    createShapeBodies(this, outlines, { isStatic: true });

    // Goal
    createObjects(this, goalOutlines, window.goalColor.replace("#", "0x"));
    createShapeBodies(this, goalOutlines, { isStatic: true, isSensor: true }, "goal");

    // Hazards
    createObjects(this, hazardOutlines, window.hazardColor.replace("#", "0x"));
    createShapeBodies(this, hazardOutlines, { isStatic: true, isSensor: true }, "hazard");

    // The sliders own these numbers. Reading them at startup matters for a
    // replayed drawing: it arrives with the speed and jump its author saved,
    // already set on the sliders, and the game has to start from those rather
    // than the defaults.
    const speedSlider = document.getElementById("speedSlider");

    if (speedSlider) {
        moveSpeed = parseInt(speedSlider.value);
        speedSlider.addEventListener("input", (e) => {
            moveSpeed = parseInt(e.target.value);
        });
    }

    const jumpSlider = document.getElementById("jumpSlider");

    if (jumpSlider) {
        jumpStrength = parseInt(jumpSlider.value);
        jumpSlider.addEventListener("input", (e) => {
            jumpStrength = parseInt(e.target.value);
        });
    }

    const loadingScreen = document.getElementById("loading-screen");

    if (loadingScreen) {
        loadingScreen.style.display = "none";
    }

}

function update() {

    if (window.gamePaused) {
        return;
    }

    if (!player) {
        return;
    }

    if (cursors.left.isDown) {
        Matter.Body.setVelocity(player, { x: -moveSpeed, y: player.velocity.y });
    }
    else if (cursors.right.isDown) {
        Matter.Body.setVelocity(player, { x: moveSpeed, y: player.velocity.y });
    }
    else {
        Matter.Body.setVelocity(player, { x: 0, y: player.velocity.y });
    }

    if (cursors.up.isDown && canJump) {
        Matter.Body.setVelocity(player, { x: player.velocity.x, y: -jumpStrength });
        canJump = false;
    }

    if (playerGraphics && playerOrigin) {
        drawPlayer(
            player.position.x - playerOrigin.x,
            player.position.y - playerOrigin.y
        );
    }

}

/**
 * Boots the scene and returns the Phaser.Game so the caller can destroy it.
 *
 * Exported instead of run at import time: the Vue Game page mounts and unmounts
 * as the user navigates, but a module only ever imports once. Booting on import
 * would start the game on the first visit and never again — and the module-level
 * state above would leak from one play into the next, so it is reset here.
 */
export function bootGame() {
    player = undefined;
    cursors = undefined;
    canJump = false;
    moveSpeed = 5;
    jumpStrength = 10;
    outlines = [];
    goalOutlines = [];
    hazardOutlines = [];
    playerOutline = null;
    playerGraphics = undefined;
    playerOrigin = null;
    levelRect = null;

    return new Phaser.Game(config);
}
