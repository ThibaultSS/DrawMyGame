<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Tests assert what a route returns, not whether the front end has been built.
    // Without this, page tests fail on a clean checkout until someone runs
    // npm run build, and adding a new Vite entry breaks unrelated tests.
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
