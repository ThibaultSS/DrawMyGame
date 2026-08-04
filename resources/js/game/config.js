// Size of the playing field. The Phaser canvas and the Matter world bounds both
// use these, so they have to stay in step.
export const WORLD = {
    width: 1500,
    height: 800,
};

export const DETECTION = {
    // How far apart two colours may be in RGB and still count as the same one.
    // A single marker never gives one exact value in a photo: lighting, paper
    // texture and JPEG compression all spread it out.
    colorTolerance: 70,
    // Anything smaller than this is noise: marker grain, compression artefacts.
    minShapeSize: 300,
    // Guard against a colour area covering half the photo, which happens when the
    // paper itself falls within the tolerance of the chosen colour.
    maxShapeSize: 100000,
    // How far Douglas-Peucker may move the outline, in pixels.
    simplifyEpsilon: 2.5,
    // poly-decomp gets slow past a few dozen corners per shape.
    maxColliderVertices: 64,
    // Below this area a simplified shape has collapsed into a line.
    minColliderArea: 8,
};

// Neighbours clockwise, in screen coordinates (y grows downwards).
export const NEIGHBOURS = [
    [ 1,  0], // east
    [ 1,  1], // south-east
    [ 0,  1], // south
    [-1,  1], // south-west
    [-1,  0], // west
    [-1, -1], // north-west
    [ 0, -1], // north
    [ 1, -1], // north-east
];

export const WEST = 4;
