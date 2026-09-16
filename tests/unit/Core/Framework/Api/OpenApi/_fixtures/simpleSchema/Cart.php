<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Shopping cart
 *
 * @codeCoverageIgnore
 */
final class Cart extends AbstractResponse
{
    /**
     * Name of the cart
     */
    public string $name;

    #[Assert\Valid]
    public CalculatedPrice $price;

    /**
     * @var list<LineItem> All items within the cart
     */
    #[Assert\Valid]
    public array $lineItems;

    public int $totalItems;

    public bool $active;

    public float $taxRate;

    /**
     * @internal
     */
    public function __construct(
        /**
         * Context token identifying the cart
         */
        #[Assert\NotBlank]
        public string $token,
    ) {
        parent::__construct();
    }
}
