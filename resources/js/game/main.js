import Phaser from "phaser";

const config = {
    type: Phaser.AUTO,

    width: 1500,
    height: 800,

    parent: "game-container",

    backgroundColor: "#9999F8",

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


let levelData = [];
let platformObjects = [];
let outlines = [];



let isDrawing = false;

let startX = 0;
let startY = 0;

let previewRect = null;

function preload() {

    this.load.image(
        "character",
        "/assets/South_Park.png"
    );
    this.load.image(
    "levelImage",
    window.levelImage
);
console.log(window.levelImage);
}
function createPlatform(scene, platformData) {
// neemt JSON bestand op met x,y, width, height en maakt een platform aan in de scene
    const rect = scene.add.rectangle(
        platformData.x,
        platformData.y,
        platformData.width,
        platformData.height,
        0x654321
    );
    scene.matter.add.gameObject(rect, {
        isStatic: true
    });
    platformObjects.push(rect);
}

function loadLevel(scene, levelData) {
    levelData.forEach(platformData => {
        createPlatform(scene,platformData);
    });
}

//herkennen van verbonden zwarte pixels in de afbeelding en groepeert ze als vormen.
function getConnectedShapes(pixels, width, height) {
    const visited = new Set();
    const shapes = [];

    function isBlack(x, y) {
        const index = (y * width + x) * 4;
        const r = pixels[index];
        const g = pixels[index + 1];
        const b = pixels[index + 2];
        const a = pixels[index + 3];

        return (r < 30 && g < 30 && b < 30 && a > 200);
    }

    function floodFill(startX, startY) {
        const stack = [[startX, startY]];
        const shape = [];

        while (stack.length > 0) {
            const [x, y] = stack.pop();
            const key = `${x},${y}`;
            if (visited.has(key)) continue;
            visited.add(key);
            if (!isBlack(x, y)) continue;
            shape.push({ x, y });
            stack.push([x + 1, y]);
            stack.push([x - 1, y]);
            stack.push([x, y + 1]);
            stack.push([x, y - 1]);
        }

        return shape;
    }

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const key = `${x},${y}`;
            if (visited.has(key)) continue;
            if (isBlack(x, y)) {
                const shape = floodFill(x, y);
                if (shape.length > 0) {
                    shapes.push(shape);
                }
            }
        }
    }
    return shapes;
}

function getOutline(shape) {

    const pixelSet = new Set(
        shape.map(p => `${p.x},${p.y}`)
    );

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

        const isEdge = neighbors.some(
            n => !pixelSet.has(n)
        );

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

        const current =
            ordered[ordered.length - 1];

        let closestIndex = 0;
        let closestDistance = Infinity;

        remaining.forEach((point,index)=>{

            const dx =
                point.x - current.x;

            const dy =
                point.y - current.y;

            const dist =
                dx*dx + dy*dy;

            if (dist < closestDistance) {

                closestDistance = dist;
                closestIndex = index;

            }

        });

        ordered.push(
            remaining.splice(
                closestIndex,
                1
            )[0]
        );

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

//omvormen van afbeelding naar scene data
function imageToLevelData(scene) {

    const texture = scene.textures.get("levelImage");
    const source = texture.getSourceImage(); // Get image
    console.log(texture);
console.log(source);
console.log(source.width);
console.log(source.height);
    const canvas = document.createElement("canvas");
    canvas.width = source.width;
    canvas.height = source.height;
    const ctx = canvas.getContext("2d"); // canvas gemaakt
    ctx.drawImage(source, 0, 0); // foto op canvas tekenen
    const imageData = ctx.getImageData(0, 0, source.width, source.height); // Pixel data ophalen
    const pixels = imageData.data; // RGBA data in array



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
   const shapes = getConnectedShapes(
    pixels,
    source.width,
    source.height
    );

    outlines = shapes.map(shape => {

    const outline =
        getOutline(shape);

    const traced =
        traceOutline(outline);

    return simplifyOutline(
        traced,
        8
    );

});

console.log(outlines);
    
}














function create() {

/*************************PLAYER************************************ */
    player = this.matter.add.sprite(700,200,"character");
    player.setScale(0.3);
    player.setBody({
        type: "rectangle",
        width: 80,
        height: player.height * 0.25
    });
    player.setOrigin(0.50, 0.59);
    player.setFixedRotation();
/*************************PLAYER************************************ */

    this.matter.world.setBounds(0,0,1500,800);
    cursors = this.input.keyboard.createCursorKeys();
    this.input.mouse.disableContextMenu();


/*************************Building Platforms************************************ */

    imageToLevelData(this);
    loadLevel(this, levelData);
    /*************************Building platforms************************************ */


    /*************************Jumping colission************************************ */
    this.matter.world.on("collisionstart", (event) => {

    event.pairs.forEach((pair) => {

        const bodyA = pair.bodyA;
        const bodyB = pair.bodyB;


        if (
            bodyA.gameObject === player ||
            bodyB.gameObject === player
        ) {
            canJump = true;
        }
    });
});
    /*************************Jumping Collision************************************ */






        /*************************Drawing platforms visualize************************************ */

 const graphics =
        this.add.graphics();

    graphics.fillStyle(
        0x654321
    );

    outlines.forEach(shape => {

        if (shape.length < 10) return;

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

/*************************CREATE COLLISION************************************ */

    outlines.forEach(shape => {

        if (shape.length < 10) return;

        const center =
            Phaser.Physics
            .Matter
            .Matter
            .Vertices
            .centre(shape);

        const centerX =
            center.x;

        const centerY =
            center.y;

        const relativeVertices =
            shape.map(p => ({
                x: p.x - centerX,
                y: p.y - centerY
            }));

        const body =
            this.matter.add.fromVertices(
                centerX,
                centerY,
                relativeVertices,
                {
                    isStatic:true
                }
            );

        if (body) {

            const bounds =
                body.bounds;

            const bodyCenterX =
                (bounds.min.x +
                bounds.max.x)/2;

            const bodyCenterY =
                (bounds.min.y +
                bounds.max.y)/2;

            const shapeCenterX =
                shape.reduce(
                    (sum,p)=>sum+p.x,
                    0
                ) / shape.length;

            const shapeCenterY =
                shape.reduce(
                    (sum,p)=>sum+p.y,
                    0
                ) / shape.length;

            Phaser.Physics
            .Matter
            .Matter
            .Body
            .setPosition(
                body,
                {
                    x:
                    body.position.x +
                    (shapeCenterX - bodyCenterX),

                    y:
                    body.position.y +
                    (shapeCenterY - bodyCenterY)
                }
            );

        }

    });

        /*************************Drawing platforms visualize************************************ */

}















function update() {

    const speed = 5;

    if (cursors.left.isDown) {
        player.setVelocityX(-speed);
    }
    else if (cursors.right.isDown) {
        player.setVelocityX(speed);
    }
    else {
        player.setVelocityX(0);
    }


    if (cursors.up.isDown && canJump) {
    player.setVelocityY(-10);
    canJump = false;
    }


    /*************************Hover************************************ */
    /*************************Hover************************************ */

}

new Phaser.Game(config);