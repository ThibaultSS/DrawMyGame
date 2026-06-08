import Phaser from "phaser";

const config = {
    type: Phaser.AUTO,

    width: 1500,
    height: 800,

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

/****Platform****/
let levelData = [];
let platformObjects = [];
let outlines = [];

/****Platform****/
let goalOutlines = [];
let playerOutline = null;
let playerGraphics;
let hazardOutlines = [];

let isDrawing = false;

let startX = 0;
let startY = 0;

let previewRect = null;

function preload() {
    this.load.image("character","/assets/South_Park.png");
    this.load.image("levelImage",window.levelImage);
}
/**************************COLOR********************** */
function hexToRgb(hex) {

    hex = hex.replace("#", "");

    return {
        r: parseInt(hex.substring(0,2),16),
        g: parseInt(hex.substring(2,4),16),
        b: parseInt(hex.substring(4,6),16)
    };

}
function matchesColor(
    x,
    y,
    pixels,
    width,
    targetColor,
    tolerance = 70
) {

    const index =
        (y * width + x) * 4;

    const r = pixels[index];
    const g = pixels[index + 1];
    const b = pixels[index + 2];

    const distance =
        Math.sqrt(
            (r - targetColor.r) ** 2 +
            (g - targetColor.g) ** 2 +
            (b - targetColor.b) ** 2
        );

    return distance < tolerance;

}
/******************************COLOR********************** */





//herkennen van verbonden zwarte pixels in de afbeelding en groepeert ze als vormen.
function getConnectedShapes(pixels,width,height,colorCheck) {
    const visited = new Set();
    const shapes = [];
    function floodFill(startX, startY) {
        const stack = [[startX,startY]];
        const shape = [];
        while (stack.length > 0) {
            const [x,y] = stack.pop();
            const key = `${x},${y}`;

            if (visited.has(key))continue;
            visited.add(key);
            if (!colorCheck(x,y,pixels,width)) continue;

            shape.push({x,y});
            if(shape.length > 100000){
    console.log("Shape exceeded limit");
    return shape;
}
            stack.push([x+1,y]);
            stack.push([x-1,y]);
            stack.push([x,y+1]);
            stack.push([x,y-1]);
        }
        return shape;
    }

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const key =`${x},${y}`;

            if (visited.has(key)) continue;

            if (colorCheck(x,y,pixels,width)) {
                const shape =floodFill(x,y);
                const MIN_SIZE = 300;
                if (shape.length > MIN_SIZE) {
                    shapes.push(shape);
                }
            }
        }
    }
    return shapes;
}

function getOutline(shape) {
    const pixelSet = new Set(shape.map(p => `${p.x},${p.y}`));
    const outline = [];

    shape.forEach(pixel => {
        const x = pixel.x;
        const y = pixel.y;
        const neighbors = [
            `${x+1},${y}`,
            `${x-1},${y}`,
            `${x},${y+1}`,
            `${x},${y-1}`
        ];

        const isEdge = neighbors.some(n => !pixelSet.has(n));
        if (isEdge) {
            outline.push(pixel);
        }
    });
    return outline;
}

function traceOutline(outline) {
    const remaining = [...outline];
    const ordered = [];
    ordered.push(remaining.shift());

    while (remaining.length > 0) {
        const current = ordered[ordered.length - 1];

        let closestIndex = 0;
        let closestDistance = Infinity;

        remaining.forEach((point,index)=>{
            const dx = point.x - current.x;
            const dy = point.y - current.y;
            const dist = dx*dx + dy*dy;

            if (dist < closestDistance) {
                closestDistance = dist;
                closestIndex = index;
            }
        });

        ordered.push(remaining.splice(closestIndex,1)[0]);
    }
    return ordered;
}

function simplifyOutline(outline, step = 10) {
    const simplified = [];
    for (let i = 0; i < outline.length; i += step) {
        simplified.push(outline[i]);
    }
    return simplified;
}
function createRectangleBodies(scene, shapes, options = {}, label = null) {

    shapes.forEach(shape => {

        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;

        shape.forEach(point => {

            minX = Math.min(minX, point.x);
            minY = Math.min(minY, point.y);

            maxX = Math.max(maxX, point.x);
            maxY = Math.max(maxY, point.y);

        });

        const width = maxX - minX;
        const height = maxY - minY;

        const centerX = minX + width / 2;
        const centerY = minY + height / 2;

        const body = scene.matter.add.rectangle(
            centerX,
            centerY,
            width,
            height,
            options
        );

        if(label){
            body.label = label;
        }

    });

}

//omvormen van afbeelding naar scene data
function imageToLevelData(scene) {
    const texture = scene.textures.get("levelImage");
    const source = texture.getSourceImage(); // Get image
    const scaleX = 1500 / source.width;
    const scaleY = 800 / source.height;
    const canvas = document.createElement("canvas");
    canvas.width = source.width;
    canvas.height = source.height;
    const ctx = canvas.getContext("2d"); // canvas gemaakt
    ctx.drawImage(source, 0, 0); // foto op canvas tekenen
    const imageData = ctx.getImageData(0, 0, source.width, source.height); // Pixel data ophalen
    const pixels = imageData.data; // RGBA data in array









    const platformColor =
    hexToRgb(
        window.platformColor
    );

const goalColor =
    hexToRgb(
        window.goalColor
    );

const playerColor =
    hexToRgb(
        window.playerColor
    );
    const hazardColor =
    hexToRgb(
        window.hazardColor
    );
    /*
    for (let y = 0; y < source.height; y++) { 
        let runStart = null; 
        let runLength = 0; 

        for (let x = 0; x < source.width; x++) {
            const index = (y * source.width + x) * 4;
            const r = pixels[index];
            const g = pixels[index + 1];
            const b = pixels[index + 2];
            const a = pixels[index + 3];

            const isBlack = (r < 30 && g < 30 && b < 30 && a > 200);

            if (isBlack) {
                if (runStart === null) {
                    runStart = x;
                    runLength = 1;
                } else {
                    runLength++;
                }
            } else {
                if (runStart !== null) {
                    levelData.push({
                        x: runStart + runLength / 2,
                        y: y,
                        width: runLength,
                        height: 1
                    });
                    runStart = null;
                    runLength = 0;
                }
            }
        }

        if (runStart !== null) {
            levelData.push({
                x: runStart + runLength / 2,
                y: y,
                width: runLength,
                height: 1
            });
        }
    }

    console.log("Platforms created:", levelData.length);
}
    */
   /******************************************
   const shapes = getConnectedShapes(pixels, source.width, source.height);
    shapes.forEach(shape => {
        const xs = shape.map(p => p.x);
        const ys = shape.map(p => p.y);
        const minX = Math.min(...xs);
        const maxX = Math.max(...xs);
        const minY = Math.min(...ys);
        const maxY = Math.max(...ys);
        levelData.push({
            x: minX + (maxX - minX) / 2,
            y: minY + (maxY - minY) / 2,
            width: maxX - minX + 1,
            height: maxY - minY + 1
        });
    });

    console.log("Platforms created:", levelData.length);
    ****************************************/
   console.log("Starting platform detection");

    const shapes =
    getConnectedShapes(
        pixels,
        source.width,
        source.height,
        (x,y,p,w)=>
            matchesColor(
                x,
                y,
                p,
                w,
                platformColor
            )
    );
    console.log("Platform detection done");

console.log("Starting goal detection");


const goalShapes =
    getConnectedShapes(
        pixels,
        source.width,
        source.height,
        (x,y,p,w)=>
            matchesColor(
                x,
                y,
                p,
                w,
                goalColor
            )
    );
console.log("Goal detection done");

console.log("Starting player detection");
const playerShapes =
    getConnectedShapes(
        pixels,
        source.width,
        source.height,
        (x,y,p,w)=>
            matchesColor(
                x,
                y,
                p,
                w,
                playerColor
            )
    );
    console.log("Player detection done");

    console.log("Starting hazard detection");
    const hazardShapes =
    getConnectedShapes(
        pixels,
        source.width,
        source.height,
        (x,y,p,w)=>
            matchesColor(
                x,
                y,
                p,
                w,
                hazardColor
            )
    );
    console.log("Hazard detection done");

    outlines = shapes.map(shape => {
        const outline =getOutline(shape);
        const traced =traceOutline(outline);
        return simplifyOutline(traced,8).map(point => ({
            x: point.x * scaleX,
            y: point.y * scaleY
        }));
    });
    goalOutlines = goalShapes.map(shape => {
        const outline = getOutline(shape);
        const traced = traceOutline(outline);
        return simplifyOutline(traced,8).map(point => ({
            x: point.x * scaleX,
            y: point.y * scaleY
        }));

    });
    
    if(playerShapes.length > 0){

        const biggestShape = playerShapes.sort((a,b) => b.length - a.length)[0];
        const outline = getOutline(biggestShape);
        const traced = traceOutline(outline);

        playerOutline =
            simplifyOutline(traced, 8)
            .map(point => ({
                x: point.x * scaleX,
                y: point.y * scaleY
            }));
    }
    hazardOutlines = hazardShapes.map(shape => {
        const outline = getOutline(shape);
        const traced = traceOutline(outline);
        return simplifyOutline(traced,8).map(point => ({
            x: point.x * scaleX,
            y: point.y * scaleY
        }));
    });
    
}












function createObjects(scene, shapes, color) {

    const graphics = scene.add.graphics();

    graphics.fillStyle(color);

    shapes.forEach(shape => {

        if (!shape || shape.length < 3) {
            return;
        }

        graphics.beginPath();

        graphics.moveTo(
            shape[0].x,
            shape[0].y
        );

        shape.forEach(point => {

            graphics.lineTo(
                point.x,
                point.y
            );

        });

        graphics.closePath();
        graphics.fillPath();

    });

}

function createPlayer(scene) {

    if(!playerOutline){
        return;
    }

    playerGraphics =
    scene.add.graphics();

    playerGraphics.fillStyle(
    parseInt(
        window.playerColor.replace("#","0x")
    )
);

    playerGraphics.beginPath();

    playerGraphics.moveTo(
        playerOutline[0].x,
        playerOutline[0].y
    );

    playerOutline.forEach(point => {

        playerGraphics.lineTo(
            point.x,
            point.y
        );

    });

    playerGraphics.closePath();
    playerGraphics.fillPath();
    playerGraphics.setDepth(1000);

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    playerOutline.forEach(p => {

        minX = Math.min(minX,p.x);
        minY = Math.min(minY,p.y);

        maxX = Math.max(maxX,p.x);
        maxY = Math.max(maxY,p.y);

    });

    const width =
        maxX - minX;

    const height =
        maxY - minY;

    const centerX =
        minX + width / 2;

    const centerY =
        minY + height / 2;

    player = scene.matter.add.rectangle(
    centerX,
    centerY,
    width,
    height,
    {
        isStatic: false
    }
);


    //****************************************TILIT******************* */
    Phaser.Physics.Matter.Matter.Body.setInertia(
        player,
        Infinity
    );
        //****************************************TILIT******************* */

}

function showPopup(message) {
    window.gamePaused = true;

    document.getElementById('popup-message').textContent = message;
    document.getElementById('popup').style.display = 'flex';

    if(message === "You won!"){

        confetti({
            particleCount: 150,
            spread: 100,
            origin: {
                y: 0.4
            }
        });

    }
}

function create() {
/*************************PLAYER************************************ */
    
/*************************PLAYER************************************ */

    this.matter.world.setBounds(0,0,1500,800);
    cursors = this.input.keyboard.createCursorKeys();
    this.input.mouse.disableContextMenu();


/*************************Building Platforms************************************ */

    imageToLevelData(this);

    /*************************Building platforms************************************ */

    createPlayer(this);
    /*************************Jumping colission************************************ */
this.matter.world.on(
    "collisionstart",
    (event)=>{
        event.pairs.forEach(pair=>{
            const bodyA = pair.bodyA;
            const bodyB = pair.bodyB;
            // Jump logic
            if(
                bodyA === player ||
                bodyB === player
            ){
                canJump = true;
            }
            // Win logic
            const goalCollision =
                bodyA.label === "goal" ||
                bodyB.label === "goal";
            const playerCollision =
                bodyA === player ||
                bodyB === player;

            if(playerCollision && goalCollision){
                showPopup("You won!");
            }
            const hazardCollision =
    bodyA.label === "hazard" ||
    bodyB.label === "hazard";

if(playerCollision && hazardCollision){
    showPopup("You lost!");
}
        });
    }
    
);
    /*************************Jumping Collision************************************ */






        /*************************Drawing platforms visualize************************************ */

// Platforms
createObjects(
    this,
    outlines,
    window.platformColor.replace("#","0x")
);
createRectangleBodies(
    this,
    outlines,
    { isStatic:true }
);
// Goal
createObjects(
    this,
    goalOutlines,
    window.goalColor.replace("#","0x")
);

createRectangleBodies(
    this,
    goalOutlines,
    {
        isStatic:true,
        isSensor:true
    },
    "goal"
);
// Hazard
createObjects(
    this,
    hazardOutlines,
    window.hazardColor.replace("#","0x")
);

createRectangleBodies(
    this,
    hazardOutlines,
    {
        isStatic:true,
        isSensor:true
    },
    "hazard"
);
        /*************************Drawing platforms visualize************************************ */



        document
    .getElementById("speedSlider")
    .addEventListener("input", (e) => {

        moveSpeed =
            parseInt(e.target.value);

    });

document
    .getElementById("jumpSlider")
    .addEventListener("input", (e) => {

        jumpStrength =
            parseInt(e.target.value);

    });
    document.getElementById("loading-screen").style.display = "none";
}
















function update() {
    if(window.gamePaused){
        console.log("PAUZE");
        return;
    }
    const speed = moveSpeed;
    if (cursors.left.isDown) {
        Phaser.Physics.Matter.Matter.Body.setVelocity(
            player,
            {
                x: -speed,
                y: player.velocity.y
            }
        );
    }
    else if (cursors.right.isDown) {
        Phaser.Physics.Matter.Matter.Body.setVelocity(
            player,
            {
                x: speed,
                y: player.velocity.y
            }
        );
    }
    else {

        Phaser.Physics.Matter.Matter.Body.setVelocity(
            player,
            {
                x: 0,
                y: player.velocity.y
            }
        );
    }

    if (cursors.up.isDown && canJump) {
        Phaser.Physics.Matter.Matter.Body.setVelocity(
            player,
            {
                x: player.velocity.x,
                y: -jumpStrength
            }
        );
        canJump = false;
    }
    if(player && playerGraphics){

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    playerOutline.forEach(p => {

        minX = Math.min(minX,p.x);
        minY = Math.min(minY,p.y);

        maxX = Math.max(maxX,p.x);
        maxY = Math.max(maxY,p.y);

    });

    const originalCenterX =
        minX + (maxX - minX) / 2;

    const originalCenterY =
        minY + (maxY - minY) / 2;

    const offsetX =
        player.position.x - originalCenterX;

    const offsetY =
        player.position.y - originalCenterY;

    playerGraphics.clear();

        playerGraphics.fillStyle(
    parseInt(
        window.playerColor.replace("#","0x")
    )
);

    playerGraphics.beginPath();

    playerGraphics.moveTo(
        playerOutline[0].x + offsetX,
        playerOutline[0].y + offsetY
    );

    playerOutline.forEach(point => {

        playerGraphics.lineTo(
            point.x + offsetX,
            point.y + offsetY
        );

    });

    playerGraphics.closePath();
    playerGraphics.fillPath();

}
}

new Phaser.Game(config);