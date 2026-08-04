/**
 * Entry point for the game page only.
 *
 * Importing the scene starts it: main.js ends with `new Phaser.Game(config)`.
 * That is why the game has its own entry instead of sharing app.js, which is
 * loaded on every page and would boot Phaser with no level image to read.
 */
import './game/main';
