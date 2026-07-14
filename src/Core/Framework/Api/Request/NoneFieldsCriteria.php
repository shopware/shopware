<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-14 11:37:53
 */

namespace Shopware\Core\Framework\Api\Request;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Search parameters. For more information, see our documentation on [Search Queries](https://shopware.stoplight.io/docs/store-api/docs/concepts/search-queries.md#structure)
 */
#[Package('framework')]
final readonly class NoneFieldsCriteria
{
    public function __construct(
        /**
         * Search result page
         */
        public ?int $page = null,
        /**
         * Search term
         */
        public ?string $term = null,
        /**
         * Number of items per result page
         */
        public ?int $limit = null,
        /**
         * List of filters to restrict the search result. For more information, see [Search Queries > Filter](https://shopware.stoplight.io/docs/store-api/docs/concepts/search-queries.md#filter)
         *
         * @var array<string, mixed>
         */
        public ?array $filter = null,
        /**
         * @var list<string> List of ids to search for
         */
        #[Assert\All(new Assert\Type('string'))]
        public ?array $ids = null,
        /**
         * The query string to search for
         */
        public ?string $query = null,
        #[Assert\Valid]
        public ?Associations $associations = null,
        /**
         * Filters that applied without affecting aggregations. For more information, see [Search Queries > Post Filter](https://shopware.stoplight.io/docs/store-api/docs/concepts/search-queries.md#post-filter)
         *
         * @var array<string, mixed>
         */
        public ?array $postFilter = null,
        /**
         * @var list<Sort> Sorting in the search result.
         */
        #[Assert\Valid]
        public ?array $sort = null,
        /**
         * @var list<Aggregation>
         */
        #[Assert\Valid]
        public ?array $aggregations = null,
        /**
         * @var list<string> Perform groupings over certain fields
         */
        #[Assert\All(new Assert\Type('string'))]
        public ?array $grouping = null,
        #[Assert\Valid]
        public ?TotalCountMode $totalCountMode = null,
        #[Assert\Valid]
        public ?Includes $includes = null,
        #[Assert\Valid]
        public ?Excludes $excludes = null,
    ) {
    }
}
