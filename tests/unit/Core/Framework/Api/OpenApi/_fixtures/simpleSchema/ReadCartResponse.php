<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Cart
 */
final readonly class ReadCartResponse
{
    public function __construct(
        /**
         * Context token identifying the cart
         */
        #[Assert\NotBlank]
        public string $token,
        /**
         * Name of the cart
         */
        public ?string $name = null,
        #[Assert\Valid]
        public ?CalculatedPrice $price = null,
        /**
         * @var list<LineItem> All items within the cart
         */
        #[Assert\Valid]
        public ?array $lineItems = null,
        public ?int $totalItems = null,
        public ?bool $active = null,
        public ?float $taxRate = null,
    ) {
    }
}
