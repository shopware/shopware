<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-09-16 14:37:41
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class RangeFilterParameters extends AbstractDto
{
    public float $gte;

    public float $gt;

    public float $lte;

    public float $lt;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
