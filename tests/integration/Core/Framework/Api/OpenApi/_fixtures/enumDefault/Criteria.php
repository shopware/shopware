<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;

/**
 * @codeCoverageIgnore
 */
final class Criteria extends AbstractDto
{
    public const DEFAULT_TOTAL_COUNT_MODE = TotalCountMode::NONE;

    public const DEFAULT_EXACT_MODE = TotalCountMode::EXACT;

    public TotalCountMode $totalCountMode;

    public TotalCountMode $exactMode;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
