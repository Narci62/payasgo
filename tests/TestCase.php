<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\Concerns\TestDatabases;

abstract class TestCase extends BaseTestCase
{
    use TestDatabases, RefreshDatabase;
}
