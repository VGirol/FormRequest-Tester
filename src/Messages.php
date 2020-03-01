<?php

declare(strict_types=1);

namespace VGirol\FormRequestTester;

/**
 * All the messages
 */
abstract class Messages
{
    const AUTHORIZED = 'Form Request is authorized.';
    const NOT_AUTHORIZED = 'Form Request is not authorized.';
    const FAILED = 'Validation have failed.';
    const SUCCEED = 'Validation passed successfully.';
    const MISSING_ERROR = 'Failed to find a validation error for key: "%s".';
    const ERROR_NOT_MISSING = 'Failed to assert that the key "%s" is not present in errors array.';
    const MISSING_MESSAGE = 'Failed to find the validation message "%s" in the errors messages.';
}
