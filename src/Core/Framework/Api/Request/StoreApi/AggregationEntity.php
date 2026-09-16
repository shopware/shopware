<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-09-16 14:46:07
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class AggregationEntity extends AbstractDto
{
    #[Assert\Valid]
    public AggregationMetrics|AggregationEntity|AggregationFilter|AggregationTerms|AggregationHistogram|AggregationRange $aggregation;

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
         * The type of aggregation
         */
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['entity'])]
        public string $type,
        /**
         * The field you want to aggregate over.
         */
        #[Assert\NotBlank]
        public string $field,
        /**
         * The entity definition e.g "product_manufacturer".
         */
        #[Assert\NotBlank]
        public string $definition,
    ) {
    }
}
