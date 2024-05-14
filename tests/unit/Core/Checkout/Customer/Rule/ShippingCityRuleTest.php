<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Rule\ShippingCityRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(ShippingCityRule::class)]
#[Group('rules')]
class ShippingCityRuleTest extends TestCase
{
    private ShippingCityRule $rule;

    protected function setUp(): void
    {
        $this->rule = new ShippingCityRule();
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertArrayHasKey('cityName', $ruleConstraints, 'Constraint cityName not found in Rule');
        $cityName = $ruleConstraints['cityName'];
        static::assertEquals(new NotBlank(), $cityName[0]);
        static::assertEquals(new Type('string'), $cityName[1]);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $billingCity): void
    {
        $cityName = 'kyln123';
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setCity($billingCity);

        $shippingLocation = new ShippingLocation(new CountryEntity(), null, $customerAddress);
        $salesChannelContext->method('getShippingLocation')->willReturn($shippingLocation);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['cityName' => $cityName, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'operator_oq / not match / city' => [Rule::OPERATOR_EQ, false, 'kyln000'];
        yield 'operator_oq / match / city' => [Rule::OPERATOR_EQ, true, 'kyln123'];
        yield 'operator_neq / match / city' => [Rule::OPERATOR_NEQ, true, 'kyln000'];
        yield 'operator_neq / not match / city' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / not match / city' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / match / city' => [Rule::OPERATOR_EMPTY, true, ' '];
    }
}
