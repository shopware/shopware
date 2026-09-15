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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single item in the cart
 *
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class LineItem extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Product identifier
         */
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        /**
         * Number of items
         */
        #[Assert\NotNull]
        public int $quantity,
        /**
         * Display label
         */
        public ?string $label = null,
    ) {
    }
}
