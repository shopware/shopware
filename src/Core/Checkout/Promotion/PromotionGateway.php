<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

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

    public function __construct(EntityRepositoryInterface $promotionRepository)
    {
        $this->promotionRepository = $promotionRepository;
    }

    /**
     * Gets a list of all available promotions
     * within the current checkout context.
     *
     * @throws InconsistentCriteriaIdsException
     * @throws \Exception
     */
    public function getByContext(SalesChannelContext $context): EntityCollection
    {
        /** @var array $contextRules */
        $contextRules = $context->getRuleIds();

        // add conditional OR filter to either get an entry that matches any existing rule,
        // or promotions that don't have ANY rules and thus are used globally
        $criteria = new Criteria([]);
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('active', true),
                new EqualsFilter('promotion.salesChannels.salesChannelId', $context->getSalesChannel()->getId()),
                $this->getDateRangeFilter(),
                $this->getRuleConditionFilters($contextRules),
                new EqualsFilter('codeType', PromotionEntity::CODE_TYPE_NO_CODE),
            ]
        ));

        $criteria->addAssociation('personaRules');
        $criteria->addAssociation('personaCustomers');

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
                    new EqualsFilter('active', true),
                    new EqualsFilter('promotion.salesChannels.salesChannelId', $context->getSalesChannel()->getId()),
                    $this->getDateRangeFilter(),
                    new EqualsAnyFilter('code', $codes),
                ]
            )
        );

        $criteria->addAssociation('personaRules');
        $criteria->addAssociation('personaCustomers');

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
        $today = new \DateTime();
        $today = $today->setTimezone(new \DateTimeZone('UTC'));

        $todayStart = $today->format('Y-m-d H:i:s 0:0:0');
        $todayEnd = $today->format('Y-m-d H:i:s 23:59:59');

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
                new RangeFilter('validFrom', ['lte' => $todayStart]),
                new EqualsFilter('validUntil', null),
            ]
        );

        $filterActiveNoStartDate = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('validFrom', null),
                new RangeFilter('validUntil', ['gte' => $todayEnd]),
            ]
        );

        $activeDateRangeFilter = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new RangeFilter('validFrom', ['lte' => $todayStart]),
                new RangeFilter('validUntil', ['gte' => $todayEnd]),
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
     * Gets the filter for the provided context rules.
     * This will build a filter that allows promotions without any rule set
     * or with any matching one from the provided list.
     */
    private function getRuleConditionFilters(array $contextRuleIds): Filter
    {
        $filterRules = new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('scopeRuleId', null),
                        new EqualsFilter('promotion.orderRules.id', null),
                    ]
                ),
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new EqualsAnyFilter('scopeRuleId', $contextRuleIds),
                        new EqualsAnyFilter('promotion.orderRules.id', $contextRuleIds),
                    ]
                ),
            ]
        );

        return $filterRules;
    }
}
