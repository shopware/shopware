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
final readonly class Query
{
    public function __construct(
        public ?float $score = null,
        #[Assert\Valid]
        public SimpleFilter|EqualsFilter|MultiNotFilter|RangeFilter|null $query = null,
    ) {
    }
}
