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

            debug: true
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
        "/assets/Platform.png"
    );
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


function imageToLevelData(scene) {
    const texture = scene.textures.get("levelImage");
    const source = texture.getSourceImage(); // Get image

    const canvas = document.createElement("canvas");
    canvas.width = source.width;
    canvas.height = source.height;
    const ctx = canvas.getContext("2d"); // canvas gemaakt
    ctx.drawImage(source, 0, 0); // foto op canvas tekenen
    const imageData = ctx.getImageData(0, 0, source.width, source.height); // Pixel data ophalen
    const pixels = imageData.data; // RGBA data in array

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