<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoExpectExceptionMessageRule;

/**
 * @internal
 *
 * @extends RuleTestCase<NoExpectExceptionMessageRule>
 */
class NoExpectExceptionMessageRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $message = 'expectExceptionMessage() is soft-deprecated in PHPUnit 13.2 and scheduled for removal in 15.0. Use expectExceptionObject(new YourException(...)) so the exception class, code and message are asserted from a single source of truth.';

        $this->analyse([__DIR__ . '/../data/NoExpectExceptionMessage/shopware-unit-test.php'], [
            [$message, 15],
            [$message, 24],
            [$message, 33],
            [$message, 42],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoExpectExceptionMessageRule();
    }
}
