<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-14 15:22:45
 */

namespace Shopware\Core\Framework\Api\Request;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class RangeFilterParameters
{
    public function __construct(
        public float|string|null $gte = null,
        public float|string|null $gt = null,
        public float|string|null $lte = null,
        public float|string|null $lt = null,
    ) {
    }
}
