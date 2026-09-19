<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Tests\TestCase;

/*
 * Only the Laravel suite boots a framework application. The core suite runs on
 * plain Pest, which is what keeps the core classes honest about not reaching
 * for Illuminate.
 */
uses(TestCase::class)->in('Laravel');
