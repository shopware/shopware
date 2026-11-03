<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CreateDefaultsRequestNested extends AbstractDto
{
    public const DEFAULT_COUNT = 10;

    public int $count;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
