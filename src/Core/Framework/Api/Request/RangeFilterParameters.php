<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-14 12:25:03
 */

namespace Shopware\Core\Framework\Api\Request;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class RangeFilterParameters
{
    public function __construct(
        public mixed $gte = null,
        public mixed $gt = null,
        public mixed $lte = null,
        public mixed $lt = null,
    ) {
    }
}
