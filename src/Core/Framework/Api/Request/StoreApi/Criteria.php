<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-09-16 14:37:41
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Search parameters. For more information, see our documentation on [Search Queries](https://shopware.stoplight.io/docs/store-api/docs/concepts/search-queries.md#structure)
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class Criteria extends AbstractDto
{
    /**
     * Search result page
     */
    public int $page;

    /**
     * Search term
     */
    public string $term;

    /**
     * Number of items per result page
     */
    public int $limit;

    /**
     * @var list<SimpleFilter|EqualsFilter|MultiNotFilter|RangeFilter> List of filters to restrict the search result. For more information, see [Search Queries > Filter](https://shopware.stoplight.io/docs/store-api/docs/concepts/search-queries.md#filter)
     */
    #[Assert\Valid]
    public array $filter;

    /**
     * @var list<string> List of ids to search for
     */
    #[Assert\All(new Assert\Type('string'))]
    public array $ids;

    /**
     * The query string to search for
     */
    public string $query;

    /**
     * @var array<string, Criteria>
     */
    #[Assert\Valid]
    public array $associations;

    /**
     * @var list<SimpleFilter|EqualsFilter|MultiNotFilter|RangeFilter> Filters that applied without affecting aggregations. For more information, see [Search Queries > Post Filter](https://shopware.stoplight.io/docs/store-api/docs/concepts/search-queries.md#post-filter)
     */
    #[Assert\Valid]
    #[SerializedName('post-filter')]
    public array $postFilter;

    /**
     * @var list<Sort> Sorting in the search result.
     */
    #[Assert\Valid]
    public array $sort;

    /**
     * @var list<AggregationMetrics|AggregationEntity|AggregationFilter|AggregationTerms|AggregationHistogram|AggregationRange>
     */
    #[Assert\Valid]
    public array $aggregations;

    /**
     * @var list<string> Fields which should be returned in the search result.
     */
    #[Assert\All(new Assert\Type('string'))]
    public array $fields;

    /**
     * @var list<string> Perform groupings over certain fields
     */
    #[Assert\All(new Assert\Type('string'))]
    public array $grouping;

    /**
     * Whether the total for the total number of hits should be determined for the search query. none = disabled total count, exact = calculate exact total amount (slow), next-pages = calculate only for next page (fast)
     */
    #[SerializedName('total-count-mode')]
    public TotalCountMode $totalCountMode = TotalCountMode::NONE;

    /**
     * @var array<string, list<string>>
     */
    public array $includes;

    /**
     * @var array<string, list<string>>
     */
    public array $excludes;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
