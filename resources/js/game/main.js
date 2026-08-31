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
let keys;
let canJump = false;

const touch = { left: false, right: false, jump: false };

let touchCleanups = [];

let startedAt = 0;

let moveSpeed = 5;
let jumpStrength = 10;

let outlines = [];
let goalOutlines = [];
let hazardOutlines = [];
let playerOutline = null;
let playerGraphics;

let playerOrigin = null;

let levelRect = null;

function preload() {
    this.load.image("levelImage", window.levelImage);
}

function imageToLevelData(scene) {
    const source = scene.textures.get("levelImage").getSourceImage();

    levelRect = fitToWorld(source.width, source.height, WORLD);

    const canvas = document.createElement("canvas");
    canvas.width = source.width;
    canvas.height = source.height;

    const ctx = canvas.getContext("2d");
    ctx.drawImage(source, 0, 0);

    const pixels = ctx.getImageData(0, 0, source.width, source.height).data;

    const targets = [
        { key: "platform", color: hexToRgb(window.platformColor) },
        { key: "goal",     color: hexToRgb(window.goalColor) },
        { key: "player",   color: hexToRgb(window.playerColor) }
    ];

    if (window.hazardColor) {
        targets.push({ key: "hazard", color: hexToRgb(window.hazardColor) });
    }

    const detected = detectShapes(pixels, source.width, source.height, targets);

    const toWorldOutline = shape =>
        simplifyOutline(traceBoundary(shape, source.width))
            .map(point => toWorldPoint(point, levelRect));

    outlines = detected.platform.map(toWorldOutline);
    goalOutlines = detected.goal.map(toWorldOutline);
    hazardOutlines = (detected.hazard ?? []).map(toWorldOutline);

    if (detected.player.length > 0) {
        const biggest = detected.player.sort((a, b) => b.length - a.length)[0];
        playerOutline = toWorldOutline(biggest);
    }

}

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

function createShapeBodies(scene, shapeOutlines, options = {}, label = null) {
    shapeOutlines.forEach(outline => {
        const body =
            createPolygonBody(scene, outline, options) ??
            createBoundingBoxBody(scene, outline, options);

        if (body && label) {
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
            true,
            0.01,
            20
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

    Matter.Body.setInertia(player, Infinity);

}

function endLevel(won) {
    window.gamePaused = true;

    document.dispatchEvent(new CustomEvent("level-ended", {
        detail: {
            won,
            ms: won ? Math.round(performance.now() - startedAt) : null
        }
    }));

}

function create() {
    startedAt = performance.now();

    cursors = this.input.keyboard.createCursorKeys();

    keys = this.input.keyboard.addKeys("W,A,D");

    bindTouchControls();
    this.input.mouse.disableContextMenu();

    imageToLevelData(this);

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

            const playerIsA = (bodyA.parent || bodyA) === player;
            const playerIsB = (bodyB.parent || bodyB) === player;

            if (!playerIsA && !playerIsB) {
                return;
            }

            canJump = true;

            const other = playerIsA ? bodyB : bodyA;

            if (other.label === "goal") {
                endLevel(true);
            }

            if (other.label === "hazard") {
                endLevel(false);
            }

        });

    });

    createObjects(this, outlines, window.platformColor.replace("#", "0x"));
    createShapeBodies(this, outlines, { isStatic: true });

    createObjects(this, goalOutlines, window.goalColor.replace("#", "0x"));
    createShapeBodies(this, goalOutlines, { isStatic: true, isSensor: true }, "goal");

    if (window.hazardColor) {
        createObjects(this, hazardOutlines, window.hazardColor.replace("#", "0x"));
        createShapeBodies(this, hazardOutlines, { isStatic: true, isSensor: true }, "hazard");
    }

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

    document.dispatchEvent(new CustomEvent("level-ready"));

}

function update() {
    if (window.gamePaused) {
        return;
    }

    if (!player) {
        return;
    }

    const left = cursors.left.isDown || keys.A.isDown || touch.left;
    const right = cursors.right.isDown || keys.D.isDown || touch.right;
    const jump = cursors.up.isDown || keys.W.isDown || touch.jump;

    if (left) {
        Matter.Body.setVelocity(player, { x: -moveSpeed, y: player.velocity.y });
    }
    else if (right) {
        Matter.Body.setVelocity(player, { x: moveSpeed, y: player.velocity.y });
    }
    else {
        Matter.Body.setVelocity(player, { x: 0, y: player.velocity.y });
    }

    if (jump && canJump) {
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

function bindTouchControls() {
    for (const [id, key] of [["touch-left", "left"], ["touch-right", "right"], ["touch-jump", "jump"]]) {
        const button = document.getElementById(id);

        if (! button) {
            continue;
        }

        const press = (event) => {
            event.preventDefault();
            touch[key] = true;
        };

        const release = () => {
            touch[key] = false;
        };

        button.addEventListener("pointerdown", press);
        button.addEventListener("pointerup", release);

        button.addEventListener("pointercancel", release);
        button.addEventListener("pointerleave", release);

        touchCleanups.push(() => {
            button.removeEventListener("pointerdown", press);
            button.removeEventListener("pointerup", release);
            button.removeEventListener("pointercancel", release);
            button.removeEventListener("pointerleave", release);
        });
    }
}

export function bootGame() {
    touchCleanups.forEach((undo) => undo());
    touchCleanups = [];
    touch.left = false;
    touch.right = false;
    touch.jump = false;

    startedAt = 0;
    player = undefined;
    cursors = undefined;
    keys = undefined;
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
