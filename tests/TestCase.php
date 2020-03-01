<?php

namespace VGirol\FormRequestTester\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use PHPUnit\Framework\AssertionFailedError;
use VGirol\PhpunitException\SetExceptionsTrait;

abstract class TestCase extends BaseTestCase
{
    use SetExceptionsTrait;

    public function setAssertionFailure(?string $message = null, $code = null): void
    {
        $this->setFailure(AssertionFailedError::class, $message, $code);
    }
}
