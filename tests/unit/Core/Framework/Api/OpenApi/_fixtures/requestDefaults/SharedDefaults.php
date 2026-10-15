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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SharedDefaults extends AbstractDto
{
    public const DEFAULT_COUNT = 10;

    public const DEFAULT_MODE = DefaultMode::NONE;

    public int $count;

    #[Assert\Valid]
    public SharedDefaultsNested $nested;

    public DefaultMode $mode;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
