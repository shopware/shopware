<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Test;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoAssertsOnObjectsRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<NoAssertsOnObjectsRule>
 */
class NoAssertsOnObjectsRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/../data/NoAssertOnResponseObject/shopware-unit-test.php'], [
            [
                'Asserting for equality with Response Objects is not allowed. Responses contain a date time as header, and thus those comparisons are time sensitive and thus flaky. Please assert on the properties of the Response you are interested in directly or use the `AssertResponseHelper`.',
                20,
            ],
            [
                'Asserting for equality with Response Objects is not allowed. Responses contain a date time as header, and thus those comparisons are time sensitive and thus flaky. Please assert on the properties of the Response you are interested in directly or use the `AssertResponseHelper`.',
                41,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoAssertsOnObjectsRule();
    }
}
