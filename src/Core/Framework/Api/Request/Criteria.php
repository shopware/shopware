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

#[Package('framework')]
final readonly class Criteria
{
    public function __construct(
        public ?int $page = null,
        public ?int $limit = null,
        /**
         * @var array<string, mixed>
         */
        public ?array $filter = null,
        /**
         * @var list<Sort>
         */
        #[Assert\Valid]
        public ?array $sort = null,
        /**
         * @var array<string, mixed>
         */
        public ?array $postFilter = null,
        public mixed $associations = null,
        /**
         * @var array<string, mixed>
         */
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
        #[Assert\Valid]
        public TotalCountMode $totalCountMode = 'none',
        /**
         * @var list<string>
         */
        #[Assert\All(new Assert\Type('string'))]
        public ?array $ids = null,
        public mixed $includes = null,
        public mixed $excludes = null,
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
