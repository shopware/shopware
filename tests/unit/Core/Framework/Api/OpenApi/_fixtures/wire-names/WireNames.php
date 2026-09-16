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
use Symfony\Component\JsonStreamer\Attribute\StreamedName;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class WireNames extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        #[SerializedName('total-count-mode')]
        #[StreamedName('total-count-mode')]
        public int $totalCountMode,
        #[SerializedName('post-filter')]
        #[StreamedName('post-filter')]
        public ?string $postFilter = null,
        public ?string $camelCase = null,
        #[SerializedName('snake_case')]
        #[StreamedName('snake_case')]
        public ?string $snakeCase = null,
        #[SerializedName('quote\'field')]
        #[StreamedName('quote\'field')]
        public ?string $quoteField = null,
    ) {
    }
}
