<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\Shared;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SharedDefaultsNested extends AbstractDto
{
    public const DEFAULT_ACTIVE = true;

    public bool $active;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
