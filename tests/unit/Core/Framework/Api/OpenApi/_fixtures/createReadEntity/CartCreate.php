<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload for creating a new cart
 */
final readonly class CartCreate
{
    public function __construct(
        /**
         * Name of the cart, e.g. guest-cart
         */
        #[Assert\NotBlank]
        public string $name,
        /**
         * @var list<LineItem> Initial line items to add to the cart
         */
        #[Assert\Valid]
        public ?array $lineItems = null,
    ) {
    }
}
