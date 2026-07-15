<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-15 11:22:29
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class RangeFilterParameters
{
    public function __construct(
        public ?float $gte = null,
        public ?float $gt = null,
        public ?float $lte = null,
        public ?float $lt = null,
    ) {
    }
}
