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
final class AggregationMetrics extends AbstractDto
{
    /**
     * @internal
     */
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
