<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-14 15:33:06
 */

namespace Shopware\Core\Framework\Api\Request;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

#[Package('framework')]
final readonly class Criteria
{
    public function __construct(
        public ?int $page = null,
        public ?int $limit = null,
        /**
         * @var list<SimpleFilter|EqualsFilter|MultiNotFilter|RangeFilter>
         */
        #[Assert\Valid]
        public ?array $filter = null,
        /**
         * @var list<Sort>
         */
        #[Assert\Valid]
        public ?array $sort = null,
        /**
         * @var list<SimpleFilter|EqualsFilter|MultiNotFilter|RangeFilter>
         */
        #[Assert\Valid]
        public ?array $postFilter = null,
        /**
         * @var array<string, Criteria>
         */
        #[Assert\Valid]
        public ?array $associations = null,
        /**
         * @var list<AggregationMetrics|AggregationEntity|AggregationFilter|AggregationTerms|AggregationHistogram|AggregationRange>
         */
        #[Assert\Valid]
        public ?array $aggregations = null,
        /**
         * @var list<string>
         */
        #[Assert\All(new Assert\Type('string'))]
        public ?array $grouping = null,
        /**
         * @var list<string>
         */
        #[Assert\All(new Assert\Type('string'))]
        public ?array $fields = null,
        /**
         * Whether the total for the total number of hits should be determined for the search query. none = disabled total count, exact = calculate exact total amount (slow), next-pages = calculate only for next page (fast)
         */
        #[Assert\Choice(choices: ['none', 'exact', 'next-pages'])]
        public string $totalCountMode = 'none',
        /**
         * @var list<string>
         */
        #[Assert\All(new Assert\Type('string'))]
        public ?array $ids = null,
        /**
         * Specify the fields that should be returned for the given entities. Object key needs to be the entity name, and the list of fields needs to be the value. Fields will not be included, if they are also specified in the excludes. Note that the include fields will only be stripped on the API-Level, consider using the `fields` parameter for performance reasons. To return a DAL extension, list the extension by its name (for example `myExtension`); the `extensions` wrapper is then kept automatically. Listing the keyword `extensions` returns all extensions.
         *
         * @var array<string, list<string>>
         */
        public ?array $includes = null,
        /**
         * Specify the fields that should be excluded from the response for the given entities. Object key needs to be the entity name, and the list of fields needs to be the value. Note that the exclude fields will only be stripped on the API-Level, consider using the `fields` parameter for performance reasons. Use an extension name to remove a single extension, or the keyword `extensions` to remove all of them.
         *
         * @var array<string, list<string>>
         */
        public ?array $excludes = null,
        /**
         * Search term
         */
        public ?string $term = null,
        /**
         * The query string to search for
         */
        public ?string $query = null,
    ) {
    }
}
