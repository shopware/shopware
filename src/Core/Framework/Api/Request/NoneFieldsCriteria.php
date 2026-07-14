<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-14 12:24:14
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
        /**
         * @var array<string, mixed>
         */
        public ?array $associations = null,
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
         * @var array<string, mixed>
         */
        public ?array $aggregations = null,
        /**
         * @var list<string> Perform groupings over certain fields
         */
        #[Assert\All(new Assert\Type('string'))]
        public ?array $grouping = null,
        /**
         * Whether the total for the total number of hits should be determined for the search query. none = disabled total count, exact = calculate exact total amount (slow), next-pages = calculate only for next page (fast)
         */
        #[Assert\Choice(choices: ['none', 'exact', 'next-pages'])]
        public string $totalCountMode = 'none',
        /**
         * Specify the fields that should be returned for the given entities. Object key needs to be the entity name, and the list of fields needs to be the value. Fields will not be included, if they are also specified in the excludes. Note that the include fields will only be stripped on the API-Level, consider using the `fields` parameter for performance reasons. To return a DAL extension, list the extension by its name (for example `myExtension`); the `extensions` wrapper is then kept automatically. Listing the keyword `extensions` returns all extensions.
         *
         * @var array<string, mixed>
         */
        public ?array $includes = null,
        /**
         * Specify the fields that should be excluded from the response for the given entities. Object key needs to be the entity name, and the list of fields needs to be the value. Note that the exclude fields will only be stripped on the API-Level, consider using the `fields` parameter for performance reasons. Use an extension name to remove a single extension, or the keyword `extensions` to remove all of them.
         *
         * @var array<string, mixed>
         */
        public ?array $excludes = null,
    ) {
    }
}
