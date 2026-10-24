<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
final class AddLineItemRequest extends AbstractRequest
{
    public const DEFAULT_QUANTITY = 1;

    public int $quantity;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $productId,
    ) {
    }
}
