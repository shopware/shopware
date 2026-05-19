<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\View;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Template\Calculation\NetAmount;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TaxCategory;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\UnitCode;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Precomputed view of a single billable line item.
 *
 * Shared across document types (invoice, delivery note, credit note, …) that render order
 * positions in structured form.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class LineItemView
{
    /**
     * Used when the product carries no `purchaseUnit` — a basis quantity is required to compute
     * the per-unit price.
     */
    final public const DEFAULT_BASIS_QUANTITY = 1.0;

    public function __construct(
        public string $lineId,
        public ?string $productNumber,
        public ?string $ean,
        public string $name,
        public float $quantity,
        public float $basisQuantity,
        public UnitCode $unitCode,
        public float $netUnitPrice,
        public float $lineTotal,
        public TaxCategory $taxCategory,
        public float $taxRate,
    ) {
    }

    /**
     * @return list<self>
     */
    public static function listFromOrder(OrderEntity $order): array
    {
        $isGross = NetAmount::isOrderGross($order);

        $items = [];
        self::appendLineItems($order, $order->getLineItems(), $isGross, '', $items);

        return $items;
    }

    /**
     * @param list<self> $items
     */
    private static function appendLineItems(
        OrderEntity $order,
        ?OrderLineItemCollection $lineItems,
        bool $isGross,
        string $parentPosition,
        array &$items,
    ): void {
        foreach ($lineItems ?? [] as $lineItem) {
            self::appendLineItem($order, $lineItem, $isGross, $parentPosition, $items);
            self::appendLineItems($order, $lineItem->getChildren(), $isGross, $parentPosition . $lineItem->getPosition() . '-', $items);
        }
    }

    /**
     * @param list<self> $items
     */
    private static function appendLineItem(
        OrderEntity $order,
        OrderLineItemEntity $lineItem,
        bool $isGross,
        string $parentPosition,
        array &$items,
    ): void {
        if (!\in_array(
            $lineItem->getType(),
            [LineItem::PRODUCT_LINE_ITEM_TYPE, LineItem::CUSTOM_LINE_ITEM_TYPE],
            true,
        )) {
            return;
        }

        $price = $lineItem->getPrice();

        if ($price === null) {
            return;
        }

        $quantity = $lineItem->getQuantity();

        if ($quantity <= 0) {
            throw DocumentV2Exception::invalidOrderData(
                $order->getId(),
                'lineItem.quantity',
                \sprintf('Line item "%s" has non-positive quantity %s.', $lineItem->getIdentifier(), $quantity),
            );
        }

        $tax = $price->getCalculatedTaxes()->first();
        $totalNet = NetAmount::fromTax($tax, $price, $isGross);
        $taxRate = $tax?->getTaxRate() ?? 0.0;
        $product = $lineItem->getProduct();

        $items[] = new self(
            lineId: $parentPosition . $lineItem->getPosition(),
            productNumber: $product?->getProductNumber(),
            ean: $product?->getEan(),
            name: $lineItem->getLabel(),
            quantity: $quantity,
            basisQuantity: $product?->getPurchaseUnit() ?? self::DEFAULT_BASIS_QUANTITY,
            unitCode: UnitCode::PIECE,
            netUnitPrice: $totalNet / $quantity,
            lineTotal: $totalNet,
            taxCategory: TaxCategory::fromRate($taxRate),
            taxRate: $taxRate,
        );
    }
}
