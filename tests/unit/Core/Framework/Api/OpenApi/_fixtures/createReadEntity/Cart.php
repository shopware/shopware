<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Full cart entity as returned by the API
 *
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class Cart extends AbstractResponse
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Name of the cart, e.g. guest-cart
         */
        #[Assert\NotBlank]
        public string $name,
        /**
         * Unique identifier of the cart
         */
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        /**
         * Date and time the cart was created
         */
        #[Assert\NotBlank]
        #[Assert\DateTime(format: Defaults::STORAGE_DATE_TIME_FORMAT)]
        public string $createdAt,
        /**
         * @var list<LineItem> Initial line items to add to the cart
         */
        #[Assert\Valid]
        public ?array $lineItems = null,
        /**
         * Date and time the cart was last modified
         */
        #[Assert\DateTime(format: Defaults::STORAGE_DATE_TIME_FORMAT)]
        public ?string $updatedAt = null,
    ) {
    }
}
