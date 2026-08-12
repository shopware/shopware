<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-12 14:37:38
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class RangeFilterParameters extends AbstractRequest
{
    public function __construct(
        public ?float $gte = null,
        public ?float $gt = null,
        public ?float $lte = null,
        public ?float $lt = null,
    ) {
    }
}
