<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\IsCompanyRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class IsCompanyCustomerRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $conditionRepository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new IsCompanyRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'isCompany' => true,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testThatFilledCompanyInformationMatchesToTrue(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getCompany')
            ->willReturn('shopware AG');

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $isCompanyRule = new IsCompanyRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertTrue($isCompanyRule->match($scope));
    }

    public function testThatUnfilledCompanyInformationMatchesToFalse(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $isCompanyRule = new IsCompanyRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertFalse($isCompanyRule->match($scope));
    }

    public function testThatEmptyStringCompanyInformationMatchesToFalse(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getCompany')
            ->willReturn('');

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $isCompanyRule = new IsCompanyRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertFalse($isCompanyRule->match($scope));
    }
}
