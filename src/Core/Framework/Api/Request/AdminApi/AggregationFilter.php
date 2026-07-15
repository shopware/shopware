<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-15 11:25:19
 */

namespace Shopware\Core\Framework\Api\Request\AdminApi;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

#[Package('framework')]
final readonly class AggregationFilter
{
    public function __construct(
        /**
         * Give your aggregation an identifier, so you can find it easier
         */
        #[Assert\NotBlank]
        public string $name,
        /**
         * @var array<string, mixed>
         */
        #[Assert\NotNull]
        public array $filter,
        /**
         * The type of aggregation
         */
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['filter'])]
        public string $type = 'filter',
        #[Assert\Valid]
        public AggregationMetrics|AggregationEntity|AggregationFilter|AggregationTerms|AggregationHistogram|AggregationRange|null $aggregation = null,
    ) {
    }
}
