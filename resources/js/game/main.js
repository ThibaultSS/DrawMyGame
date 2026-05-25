import Phaser from "phaser";

const config = {
    type: Phaser.AUTO,

    width: 1500,
    height: 800,

    parent: "game-container",

    backgroundColor: "#AFAFAF",

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

/****Platform****/
let levelData = [];
let platformObjects = [];
let outlines = [];

/****Platform****/
let goalOutlines = [];


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
}
/**************************COLOR********************** */
function colorDistance(
    r1,g1,b1,
    r2,g2,b2
){

    return Math.sqrt(

        (r1-r2)**2 +
        (g1-g2)**2 +
        (b1-b2)**2

    );

}
function isRed(x,y,pixels,width){

    const index =
        (y*width+x)*4;

    const r = pixels[index];
    const g = pixels[index+1];
    const b = pixels[index+2];

    return (
        r > 100 &&
        r > g*1.5 &&
        r > b*1.5
    );
}


function isBlack(x,y,pixels,width){

    const index =
        (y*width+x)*4;

    const r = pixels[index];
    const g = pixels[index+1];
    const b = pixels[index+2];

    const brightness =
        (r+g+b)/3;

    return brightness < 100;
}
/******************************COLOR********************** */





//herkennen van verbonden zwarte pixels in de afbeelding en groepeert ze als vormen.
function getConnectedShapes(
    pixels,
    width,
    height,
    colorCheck
) {

    const visited = new Set();
    const shapes = [];

    function floodFill(startX, startY) {

        const stack = [[startX,startY]];
        const shape = [];

        while (stack.length > 0) {

            const [x,y] = stack.pop();

            const key = `${x},${y}`;

            if (visited.has(key))
                continue;

            visited.add(key);

            if (
                !colorCheck(
                    x,
                    y,
                    pixels,
                    width
                )
            ) continue;

            shape.push({x,y});

            stack.push([x+1,y]);
            stack.push([x-1,y]);
            stack.push([x,y+1]);
            stack.push([x,y-1]);

        }

        return shape;
    }

    for (let y = 0; y < height; y++) {

        for (let x = 0; x < width; x++) {

            const key =
                `${x},${y}`;

            if (
                visited.has(key)
            ) continue;

            if (
                colorCheck(
                    x,
                    y,
                    pixels,
                    width
                )
            ) {

                const shape =
                    floodFill(
                        x,
                        y
                    );

                const MIN_SIZE = 300;

if (
    shape.length > MIN_SIZE
) {
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
    const scaleX = 1500 / source.width;
    const scaleY = 800 / source.height;
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
    source.height,
    isBlack
    );

    const goalShapes = getConnectedShapes(
    pixels,
    source.width,
    source.height,
    isRed
    );

    outlines = shapes.map(shape => {

    const outline =
        getOutline(shape);

    const traced =
        traceOutline(outline);

    return simplifyOutline(
    traced,
    8
).map(point => ({
    x: point.x * scaleX,
    y: point.y * scaleY
}));

});
goalOutlines = goalShapes.map(shape => {

    const outline =
        getOutline(shape);

    const traced =
        traceOutline(outline);

    return simplifyOutline(
    traced,
    8
).map(point => ({
    x: point.x * scaleX,
    y: point.y * scaleY
}));

});
    
}












function createObjects(
    scene,
    shapes,
    color,
    physicsOptions = {},
    label = null
) {
console.log("Creating:", shapes);
    const graphics = scene.add.graphics();

    graphics.fillStyle(color);

    shapes.forEach(shape => {

        if (
    !shape ||
    shape.length < 10
){
    return;
}

        // Draw object

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


        // Physics

        const center =
            Phaser.Physics.Matter
            .Matter.Vertices
            .centre(shape);

        const centerX = center.x;
        const centerY = center.y;

        const relativeVertices =
            shape.map(p => ({
                x: p.x - centerX,
                y: p.y - centerY
            }));
            if(relativeVertices.length < 3){
    return;
}
        let body = null;

try{

    body =
        scene.matter.add.fromVertices(
            centerX,
            centerY,
            relativeVertices,
            physicsOptions
        );

}catch(error){

    console.log(
        "Invalid shape skipped:",
        shape
    );

    return;
}

        if(body){

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

            Phaser.Physics.Matter
            .Matter.Body
            .setPosition(
                body,
                {
                    x:
                    body.position.x +
                    (shapeCenterX-bodyCenterX),

                    y:
                    body.position.y +
                    (shapeCenterY-bodyCenterY)
                }
            );

            if(label){

                body.label = label;

                if(body.parts){

                    body.parts.forEach(part=>{

                        part.label =
                            label;

                    });

                }

            }

        }

    });

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

    /*************************Building platforms************************************ */


    /*************************Jumping colission************************************ */
this.matter.world.on(
    "collisionstart",
    (event)=>{

        event.pairs.forEach(pair=>{

            const bodyA = pair.bodyA;
            const bodyB = pair.bodyB;

            // Jump logic
            if(
                bodyA.gameObject===player ||
                bodyB.gameObject===player
            ){
                canJump = true;
            }

            // Win logic
            const playerCollision =
                bodyA.gameObject===player ||
                bodyB.gameObject===player;

            const goalCollision =
                bodyA.label==="goal" ||
                bodyB.label==="goal";

            if(
                playerCollision &&
                goalCollision
            ){
                alert("You won");
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
    0x654321,
    {
        isStatic:true
    }
);


// Goal

createObjects(
    this,
    goalOutlines,
    0xff0000,
    {
        isStatic:true,
        isSensor:true
    },
    "goal"
);

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