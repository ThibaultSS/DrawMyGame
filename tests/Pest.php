<?php

use Tests\TestCase;

// The suite is written as PHPUnit classes (see tests/Feature/GameTest.php), so
// all this does is bind that base TestCase — which is where withoutVite() lives —
// to everything under Feature.
pest()->extend(TestCase::class)->in('Feature');
