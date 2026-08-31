export const WORLD = {
    width: 1500,
    height: 800,
};

export const DETECTION = {
    colorTolerance: 70,
    minShapeSize: 300,
    maxShapeSize: 100000,
    simplifyEpsilon: 2.5,
    maxColliderVertices: 64,
    minColliderArea: 8,
};

export const NEIGHBOURS = [
    [ 1,  0],
    [ 1,  1],
    [ 0,  1],
    [-1,  1],
    [-1,  0],
    [-1, -1],
    [ 0, -1],
    [ 1, -1],
];

export const WEST = 4;
