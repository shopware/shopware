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
final readonly class AggregationHistogram
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
         * The type of aggregation
         */
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['histogram'])]
        public string $type = 'histogram',
        /**
         * The interval of the histogram
         */
        public ?float $interval = null,
        /**
         * The format of the histogram
         */
        public ?string $format = null,
        /**
         * The timezone of the histogram
         */
        public ?string $timeZone = null,
        #[Assert\Valid]
        public AggregationMetrics|AggregationEntity|AggregationFilter|AggregationTerms|AggregationHistogram|AggregationRange|null $aggregation = null,
    ) {
    }
}
