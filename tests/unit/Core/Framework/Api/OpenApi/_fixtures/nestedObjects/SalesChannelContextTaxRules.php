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
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class SalesChannelContextTaxRules extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public float $taxRate,
        public ?string $name = null,
    ) {
    }
}
