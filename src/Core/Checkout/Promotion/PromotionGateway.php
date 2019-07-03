<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Checkout\Promotion\Service\PromotionDateTimeServiceInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionGateway implements PromotionGatewayInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $promotionRepository;

    /**
     * @var PromotionDateTimeServiceInterface
     */
    private $promotionDateTimeService;

    public function __construct(EntityRepositoryInterface $promotionRepository, PromotionDateTimeServiceInterface $promotionDateTimeService)
    {
        $this->promotionRepository = $promotionRepository;
        $this->promotionDateTimeService = $promotionDateTimeService;
    }

    /**
     * Gets a list of all available active promotions that do not
     * require a code within the current checkout context.
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function getAutomaticPromotions(SalesChannelContext $context): EntityCollection
    {
        // add conditional OR filter to either get an entry that matches any existing rule,
        // or promotions that don't have ANY rules and thus are used globally
        $criteria = new Criteria([]);
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('useCodes', false),
                new EqualsFilter('active', true),
                new EqualsFilter('promotion.salesChannels.salesChannelId', $context->getSalesChannel()->getId()),
                $this->getDateRangeFilter(),
            ]
        ));

        $criteria->addAssociation('personaRules');
        $criteria->addAssociation('personaCustomers');
        $criteria->addAssociation('cartRules');
        $criteria->addAssociation('orderRules');
        $criteria->addAssociationPath('discounts.discountRules');
        $criteria->addAssociationPath('discounts.promotionDiscountPrices');

        /* @var EntityCollection $result */
        $result = $this->promotionRepository->search($criteria, $context->getContext())->getEntities();

        return $result;
    }

    /**
     * Gets a list of promotions that match the provided code.
     * It also makes sure to only return active and valid promotions.
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function getByCodes(array $codes, SalesChannelContext $context): EntityCollection
    {
        $criteria = new Criteria([]);

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('useCodes', true),
                    new EqualsAnyFilter('code', $codes),
                    new EqualsFilter('active', true),
                    new EqualsFilter('promotion.salesChannels.salesChannelId', $context->getSalesChannel()->getId()),
                    $this->getDateRangeFilter(),
                ]
            )
        );

        $criteria->addAssociation('personaRules');
        $criteria->addAssociation('personaCustomers');
        $criteria->addAssociation('cartRules');
        $criteria->addAssociation('orderRules');
        $criteria->addAssociationPath('discounts.discountRules');
        $criteria->addAssociationPath('discounts.promotionDiscountPrices');

        /* @var EntityCollection $result */
        $result = $this->promotionRepository->search($criteria, $context->getContext())->getEntities();

        return $result;
    }

    /**
     * This is the basic filter that can be added
     * to get only promotions valid for the current time period.
     */
    private function getDateRangeFilter(): Filter
    {
        /** @var string $nowAsString */
        $nowAsString = $this->promotionDateTimeService->getNow();

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
                new RangeFilter('validFrom', ['lte' => $nowAsString]),
                new EqualsFilter('validUntil', null),
            ]
        );

        $filterActiveNoStartDate = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('validFrom', null),
                new RangeFilter('validUntil', ['gte' => $nowAsString]),
            ]
        );

        $activeDateRangeFilter = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new RangeFilter('validFrom', ['lte' => $nowAsString]),
                new RangeFilter('validUntil', ['gte' => $nowAsString]),
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
}
