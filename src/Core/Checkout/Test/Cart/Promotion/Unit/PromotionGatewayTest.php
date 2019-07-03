<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionGateway;
use Shopware\Core\Checkout\Promotion\Service\PromotionDateTimeServiceInterface;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes\FakePromotionDateTimeService;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes\FakePromotionRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class PromotionGatewayTest extends TestCase
{
    /**
     * @var SalesChannelContext
     */
    private $checkoutContext = null;

    /**
     * @var SalesChannelEntity
     */
    private $salesChannel = null;

    /**
     * @var PromotionDateTimeServiceInterface
     */
    private $promotionDateTimeService = null;

    /**
     * @throws \ReflectionException
     */
    public function setUp(): void
    {
        $this->promotionDateTimeService = new FakePromotionDateTimeService();
        $this->salesChannel = new SalesChannelEntity();
        $this->salesChannel->setId('CH1');

        $this->checkoutContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->checkoutContext->expects(static::any())->method('getSalesChannel')->willReturn($this->salesChannel);
    }

    /**
     * This test verifies that the correct filter is being built
     * and passed on to the promotion repository.
     * We use a fake repository in this case to verify that
     * data that has been passed on.
     *
     * @test
     * @group promotions
     */
    public function testGetAutomaticPromotionsCriteria()
    {
        $fakeRepo = new FakePromotionRepository();
        $gateway = new PromotionGateway($fakeRepo, $this->promotionDateTimeService);

        /* @var SalesChannelContext $checkoutContext */
        $gateway->getAutomaticPromotions($this->checkoutContext);

        $expectedCriteria = new Criteria([]);
        $expectedCriteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('useCodes', false),
                new EqualsFilter('active', true),
                new EqualsFilter('promotion.salesChannels.salesChannelId', 'CH1'),
                $this->getExpectedDateRangeFilter(),
            ]
        ));

        $expectedCriteria->addAssociation('personaRules');
        $expectedCriteria->addAssociation('personaCustomers');
        $expectedCriteria->addAssociation('cartRules');
        $expectedCriteria->addAssociation('orderRules');
        $expectedCriteria->addAssociationPath('discounts.discountRules');
        $expectedCriteria->addAssociationPath('discounts.promotionDiscountPrices');

        static::assertEquals($expectedCriteria, $fakeRepo->getSearchedCriteria());
    }

    /**
     * This test verifies that the correct filter being built
     * and passed on to the promotion repository for our getByCodes function.
     * We also use a fake repository in here to get the criteria
     * that is being passed on and assert that object.
     *
     * @test
     * @group promotions
     */
    public function testGetByCodes()
    {
        $fakeRepo = new FakePromotionRepository();
        $gateway = new PromotionGateway($fakeRepo, $this->promotionDateTimeService);

        /* @var SalesChannelContext $checkoutContext */
        $gateway->getByCodes(['CODE-1', 'CODE-2'], $this->checkoutContext);

        $expectedCriteria = new Criteria([]);
        $expectedCriteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('useCodes', true),
            $this->getExpectedCodesFilter(['CODE-1', 'CODE-2']),
            new EqualsFilter('active', true),
            new EqualsFilter('promotion.salesChannels.salesChannelId', 'CH1'),
            $this->getExpectedDateRangeFilter(),
        ]));

        $expectedCriteria->addAssociation('personaRules');
        $expectedCriteria->addAssociation('personaCustomers');
        $expectedCriteria->addAssociation('cartRules');
        $expectedCriteria->addAssociation('orderRules');
        $expectedCriteria->addAssociationPath('discounts.discountRules');
        $expectedCriteria->addAssociationPath('discounts.promotionDiscountPrices');

        static::assertEquals($expectedCriteria, $fakeRepo->getSearchedCriteria());
    }

    /**
     * Gets the expected filter structure.
     * Our original Shopware filter should look like this
     * and must not be touched without recognizing it.
     */
    private function getExpectedDateRangeFilter(): Filter
    {
        $now = $this->promotionDateTimeService->getNow();
        $filterNoDateRange = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('validFrom', null),
                new EqualsFilter('validUntil', null),
            ]
        );

        $filterStartedNoEndDate = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new RangeFilter('validFrom', ['lte' => $now]),
                new EqualsFilter('validUntil', null),
            ]
        );

        $filterActiveNoStartDate = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('validFrom', null),
                new RangeFilter('validUntil', ['gte' => $now]),
            ]
        );

        $activeDateRangeFilter = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new RangeFilter('validFrom', ['lte' => $now]),
                new RangeFilter('validUntil', ['gte' => $now]),
            ]
        );

        $dateFilter = new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                $filterNoDateRange,
                $filterActiveNoStartDate,
                $filterStartedNoEndDate,
                $activeDateRangeFilter,
            ]
        );

        return $dateFilter;
    }

    /**
     * Gets the expected filter structure.
     * Our original Shopware filter should look like this
     * and must not be touched without recognizing it.
     */
    private function getExpectedRuleConditionFilters(array $contextRuleIds): Filter
    {
        $filterRules = new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('promotion.orderRules.id', null),
                        new EqualsFilter('promotion.cartRules.id', null),
                    ]
                ),
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new EqualsAnyFilter('promotion.orderRules.id', $contextRuleIds),
                        new EqualsAnyFilter('promotion.cartRules.id', $contextRuleIds),
                    ]
                ),
            ]
        );

        return $filterRules;
    }

    /**
     * Gets the expected filters to query for either a list of
     * provided global codes or individual codes.
     */
    private function getExpectedCodesFilter(array $codes): Filter
    {
        return new EqualsAnyFilter('code', $codes);
    }
}
