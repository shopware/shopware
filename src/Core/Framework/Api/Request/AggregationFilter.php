<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-14 15:24:15
 */

namespace Shopware\Core\Framework\Api\Request;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

#[Package('framework')]
final readonly class AggregationFilter
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        /**
         * @var array<string, mixed>
         */
        #[Assert\NotNull]
        public array $filter,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['filter'])]
        public string $type = 'filter',
        #[Assert\Valid]
        public AggregationMetrics|AggregationEntity|AggregationFilter|AggregationTerms|AggregationHistogram|AggregationRange|null $aggregation = null,
    ) {
    }
}
