<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-10 15:01:45
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class AggregationRange
{
    public function __construct(
        /**
         * Give your aggregation an identifier, so you can find it easier
         */
        #[Assert\NotBlank]
        public string $name,
        /**
         * The field you want to aggregate over.
         */
        #[Assert\NotBlank]
        public string $field,
        /**
         * The ranges of the aggregation
         *
         * @var array<string, mixed>
         */
        #[Assert\NotNull]
        public array $ranges,
        /**
         * The type of aggregation
         */
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['range'])]
        public string $type = 'range',
        #[Assert\Valid]
        public AggregationMetrics|AggregationEntity|AggregationFilter|AggregationTerms|AggregationHistogram|AggregationRange|null $aggregation = null,
    ) {
    }
}
