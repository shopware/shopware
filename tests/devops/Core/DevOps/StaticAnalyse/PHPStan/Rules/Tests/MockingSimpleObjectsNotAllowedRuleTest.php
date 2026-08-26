<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\MockingSimpleObjectsNotAllowedRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends  RuleTestCase<MockingSimpleObjectsNotAllowedRule>
 */
#[Package('framework')]
class MockingSimpleObjectsNotAllowedRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/../data/MockingSimpleObjects/shopware-unit-test.php'], [
            [
                'Mocking of Shopware\Core\Checkout\Order\OrderEntity is not allowed. The object is very basic and can be constructed',
                16,
            ],
        ]);

        $this->analyse([__DIR__ . '/../data/MockingSimpleObjects/commercial-unit-test.php'], [
            [
                'Mocking of Shopware\Core\Checkout\Order\OrderEntity is not allowed. The object is very basic and can be constructed',
                16,
            ],
        ]);

        $this->analyse([__DIR__ . '/../data/MockingSimpleObjects/parent-class-test.php'], [
            [
                'Mocking of Shopware\Core\Checkout\Order\OrderEntity is not allowed. The object is very basic and can be constructed',
                14,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new MockingSimpleObjectsNotAllowedRule(self::createReflectionProvider());
    }
}
