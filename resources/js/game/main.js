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


let platforms = [];



let isDrawing = false;

let startX = 0;
let startY = 0;

let previewRect = null;

function preload() {

    this.load.image(
        "character",
        "/assets/South_Park.png"
    );
}

function create() {

/*************************PLAYER************************************ */
    player = this.matter.add.sprite(700,200,"character");
    player.setScale(0.3);
    player.setBody({
        type: "rectangle",
        width: 80,
        height: player.height * 0.2
    });
    player.setOrigin(0.45, 0.54);
    player.setFixedRotation();
/*************************PLAYER************************************ */


    
    this.matter.world.setBounds(0,0,1500,800);
    cursors = this.input.keyboard.createCursorKeys();


/*************************Building Platforms************************************ */

    this.input.on("pointerdown", (pointer) => {
        isDrawing = true;
        startX = pointer.x;
        startY = pointer.y;

        previewRect = this.add.rectangle(startX,startY,1,1,0x00ff00,0.4);  // BELANGRIJK: START MET 1X1, ANDERS ZIE JE HET NIET
        previewRect.setOrigin(0, 0);
    });

    this.input.on("pointermove", (pointer) => { //pointer verandert tijdens het tekenen

        if (!isDrawing) return;

        const width = pointer.x - startX;
        const height = pointer.y - startY;

        previewRect.width = width;
        previewRect.height = height;
    });


    this.input.on("pointerup", (pointer) => {

        if (!isDrawing) return;

        isDrawing = false;

        const width = pointer.x - startX;
        const height = pointer.y - startY;

        const centerX = startX + width / 2;
        const centerY = startY + height / 2;


        const platformData = {
            x: centerX,
            y: centerY,
            width: Math.abs(width),
            height: Math.abs(height)
        };

        platforms.push(platformData);

        const rect = this.add.rectangle(centerX,centerY,Math.abs(width),Math.abs(height),0x654321); // Rechthoek getekentd

        //colission toeveogen
        this.matter.add.gameObject(rect, {isStatic: true});
        previewRect.destroy();
    });
    /*************************Building platforms************************************ */



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
}

new Phaser.Game(config);