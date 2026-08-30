<?php

declare(strict_types=1);

use PrimeVueKit\Tests\AuthTestCase;
use PrimeVueKit\Tests\Fixtures\ApplicationFixture;
use PrimeVueKit\Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(AuthTestCase::class)->in('Auth');

afterEach(function (): void {
    ApplicationFixture::cleanup();
});
