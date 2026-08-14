<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-14 11:56:12
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[JsonStreamable]
final class AggregationTerms extends AbstractDto
{
    /**
     * @internal
     */
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
        #[Assert\Choice(choices: ['terms'])]
        public string $type = 'terms',
        /**
         * The number of terms to return
         */
        public ?float $limit = null,
        /**
         * @var list<Sort> Sorting the aggregation result.
         */
        #[Assert\Valid]
        public ?array $sort = null,
        #[Assert\Valid]
        public AggregationMetrics|AggregationEntity|AggregationFilter|AggregationTerms|AggregationHistogram|AggregationRange|null $aggregation = null,
    ) {
    }
}
