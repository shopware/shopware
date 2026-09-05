<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;

/**
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class Media extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        public ?string $url = null,
        public ?string $alt = null,
    ) {
    }
}
