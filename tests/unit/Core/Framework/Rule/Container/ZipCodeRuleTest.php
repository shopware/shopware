<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\ZipCodeRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ZipCodeRule::class)]
class ZipCodeRuleTest extends TestCase
{
    public function testMatchZipCodeThrowsExceptionWithUnsupportedOperator(): void
    {
        $this->expectExceptionObject(
            RuleException::unsupportedOperator(
                'bad-operator',
                ZipCodeRule::class
            )
        );

        $rule = new class('bad-operator', ['12345']) extends ZipCodeRule {
            final public const RULE_NAME = 'zipCodeTest';

            public function match(RuleScope $scope): bool
            {
                return false;
            }

            public function matchAddress(CustomerAddressEntity $address): bool
            {
                return $this->matchZipCode($address);
            }
        };

        $rule->matchAddress(new CustomerAddressEntity());
    }

    public function testMatchZipCodeThrowsExceptionWithUnsupportedValue(): void
    {
        $this->expectExceptionObject(
            RuleException::unsupportedValue(
                'NULL',
                ZipCodeRule::class
            )
        );

        $rule = new class(Rule::OPERATOR_EQ) extends ZipCodeRule {
            final public const RULE_NAME = 'zipCodeTest';

            public function match(RuleScope $scope): bool
            {
                return false;
            }

            public function matchAddress(CustomerAddressEntity $address): bool
            {
                return $this->matchZipCode($address);
            }
        };

        $rule->matchAddress(new CustomerAddressEntity());
    }
}
