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
final readonly class AggregationMetrics
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['avg', 'count', 'max', 'min', 'stats', 'sum'])]
        public string $type,
        #[Assert\NotBlank]
        public string $field,
    ) {
    }
}
