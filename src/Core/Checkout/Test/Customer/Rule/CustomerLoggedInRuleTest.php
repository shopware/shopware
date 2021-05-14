<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerLoggedInRule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class CustomerLoggedInRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private CustomerLoggedInRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CustomerLoggedInRule();
    }

    public function testName(): void
    {
        static::assertSame('customerLoggedIn', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('isLoggedIn', $ruleConstraints, 'Constraint isLoggedIn not found in Rule');

        $isLoggedIn = $ruleConstraints['isLoggedIn'];
        static::assertEquals(new NotNull(), $isLoggedIn[0]);
        static::assertEquals(new Type(['type' => 'bool']), $isLoggedIn[1]);
    }

    /**
     * @dataProvider getCaseTestMatchValues
     */
    public function testRuleMatchingOrNot(bool $isLoggedIn, bool $hasCustomer, bool $isMatching): void
    {
        $this->rule->assign(['isLoggedIn' => $isLoggedIn]);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = $hasCustomer ? $this->createMock(CustomerEntity::class) : null;

        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $match = $this->rule->match($scope);

        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public function getCaseTestMatchValues(): array
    {
        return [
            'Not Logged In / Has customer / Not Match' => [false, true, false],
            'Not Logged In / Has not customer/ Match' => [false, false, true],
            'Logged In / Has customer/ Match' => [true, true, true],
            'Logged In / Has not customer/ Not Match' => [true, false, false],
        ];
    }
}
