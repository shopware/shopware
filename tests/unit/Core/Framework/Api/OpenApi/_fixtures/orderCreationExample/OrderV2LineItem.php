<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-09-17 14:18:33
 */

namespace Shopware\Core\Checkout\Order\SalesChannel\Order;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Display-ready snapshot of an order line item.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2LineItem extends AbstractDto
{
    /**
     * Identifier of the referenced product, promotion, or extension-defined resource.
     */
    public ?string $referenceId;

    /**
     * Product number for a product line item.
     */
    public ?string $sku;

    /**
     * Additional line-item description captured in the order language, or `null` when unavailable.
     */
    public ?string $description;

    /**
     * Primary image captured for the line item, or `null` when unavailable.
     */
    #[Assert\Valid]
    public ?OrderV2Image $image;

    /**
     * @var list<OrderV2LineItemOption> Selected variant options in display order.
     */
    #[Assert\Valid]
    public array $options;

    /**
     * @internal
     */
    public function __construct(
        /**
         * Identifier of the order line item.
         */
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        /**
         * Persisted Shopware order line-item type.
         */
        #[Assert\NotNull]
        public OrderV2LineItemType $type,
        /**
         * Line item label captured in the order language.
         */
        #[Assert\NotBlank]
        public string $label,
        /**
         * Number of units represented by this line item.
         */
        #[Assert\NotNull]
        public int $quantity,
        /**
         * Calculated price for one unit, using the order currency.
         */
        #[Assert\NotNull]
        public float $unitPrice,
        /**
         * Calculated price for the full line-item quantity, using the order currency.
         */
        #[Assert\NotNull]
        public float $totalPrice,
        /**
         * Sort position used when displaying sibling line items.
         */
        #[Assert\NotNull]
        public int $position,
        /**
         * @var list<OrderV2LineItem> Nested line items belonging to this line item, in display order.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public array $children,
    ) {
    }
}
