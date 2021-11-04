<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\IsNewsletterRecipientRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class IsNewsletterRecipientRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepositoryInterface $ruleRepository;

    private EntityRepositoryInterface $conditionRepository;

    private Context $context;

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
                'type' => (new IsNewsletterRecipientRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'isNewsletterRecipient' => true,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testExistingNewsletterSalesChannelIdMatchesToTrue(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getNewsletterSalesChannelids')
            ->willReturn([Uuid::randomHex() => 'foo', Uuid::randomHex() => 'bar']);

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext->method('getSalesChannelId')
            ->willReturn('foo');
        $isCompanyRule = new IsNewsletterRecipientRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertTrue($isCompanyRule->match($scope));
    }

    public function testEmptyNewsletterSalesChannelIdsMatchesToFalse(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $isCompanyRule = new IsNewsletterRecipientRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertFalse($isCompanyRule->match($scope));
    }

    public function testMissingNewsletterSalesChannelIdMatchesToFalse(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getNewsletterSalesChannelids')
            ->willReturn([Uuid::randomHex() => 'bar']);

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext->method('getSalesChannelId')
            ->willReturn('foo');
        $isCompanyRule = new IsNewsletterRecipientRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertFalse($isCompanyRule->match($scope));
    }
}
