<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\View;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
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
     * BT-146 is written per billed unit, so the price base quantity (BT-149) must be 1,
     * otherwise the PEPPOL-EN16931-R120 line amount calculation breaks.
     */
    final public const PRICE_BASIS_QUANTITY = 1.0;

    /**
     * @var list<string>
     */
    private const BILLABLE_TYPES = [
        LineItem::PRODUCT_LINE_ITEM_TYPE,
        LineItem::CUSTOM_LINE_ITEM_TYPE,
    ];

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
    public static function listFromOrder(OrderEntity $order, bool $allowNegative = false): array
    {
        return self::listFromTypes($order, self::BILLABLE_TYPES, $allowNegative);
    }

    /**
     * @return list<self>
     */
    public static function listFromCreditItems(OrderEntity $order): array
    {
        $isGross = NetAmount::isOrderGross($order);

        $items = [];
        self::appendCreditLineItems($order, $order->getLineItems(), $isGross, '', $items);

        return $items;
    }

    /**
     * @param list<string> $acceptedTypes
     *
     * @return list<self>
     */
    private static function listFromTypes(OrderEntity $order, array $acceptedTypes, bool $allowNegative): array
    {
        $isGross = NetAmount::isOrderGross($order);

        $items = [];
        self::appendLineItems($order, $order->getLineItems(), $isGross, '', $items, $acceptedTypes, $allowNegative);

        return $items;
    }

    /**
     * @param list<self> $items
     * @param list<string> $acceptedTypes
     */
    private static function appendLineItems(
        OrderEntity $order,
        ?OrderLineItemCollection $lineItems,
        bool $isGross,
        string $parentPosition,
        array &$items,
        array $acceptedTypes,
        bool $allowNegative,
    ): void {
        foreach ($lineItems ?? [] as $lineItem) {
            self::appendLineItem($order, $lineItem, $isGross, $parentPosition, $items, $acceptedTypes, $allowNegative);
            self::appendLineItems($order, $lineItem->getChildren(), $isGross, $parentPosition . $lineItem->getPosition() . '-', $items, $acceptedTypes, $allowNegative);
        }
    }

    /**
     * @param list<self> $items
     * @param list<string> $acceptedTypes
     */
    private static function appendLineItem(
        OrderEntity $order,
        OrderLineItemEntity $lineItem,
        bool $isGross,
        string $parentPosition,
        array &$items,
        array $acceptedTypes,
        bool $allowNegative,
    ): void {
        if (!\in_array($lineItem->getType(), $acceptedTypes, true)) {
            return;
        }

        $price = $lineItem->getPrice();

        if ($price === null) {
            return;
        }

        $quantity = $lineItem->getQuantity();

        if ($quantity === 0) {
            throw DocumentV2Exception::invalidOrderData(
                $order->getId(),
                'lineItem.quantity',
                \sprintf('Line item "%s" has zero quantity.', $lineItem->getIdentifier()),
            );
        }

        if ($quantity < 0 && !$allowNegative) {
            throw DocumentV2Exception::invalidOrderData(
                $order->getId(),
                'lineItem.quantity',
                \sprintf('Line item "%s" has negative quantity.', $lineItem->getIdentifier()),
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
            basisQuantity: self::PRICE_BASIS_QUANTITY,
            unitCode: UnitCode::PIECE,
            netUnitPrice: $totalNet / $quantity,
            lineTotal: $totalNet,
            taxCategory: TaxCategory::fromRate($taxRate),
            taxRate: $taxRate,
        );
    }

    /**
     * @param list<self> $items
     */
    private static function appendCreditLineItems(
        OrderEntity $order,
        ?OrderLineItemCollection $lineItems,
        bool $isGross,
        string $parentPosition,
        array &$items,
    ): void {
        foreach ($lineItems ?? [] as $lineItem) {
            self::appendCreditLineItem($order, $lineItem, $isGross, $parentPosition, $items);
            self::appendCreditLineItems($order, $lineItem->getChildren(), $isGross, $parentPosition . $lineItem->getPosition() . '-', $items);
        }
    }

    /**
     * @param list<self> $items
     */
    private static function appendCreditLineItem(
        OrderEntity $order,
        OrderLineItemEntity $lineItem,
        bool $isGross,
        string $parentPosition,
        array &$items,
    ): void {
        if ($lineItem->getType() !== LineItem::CREDIT_LINE_ITEM_TYPE) {
            return;
        }

        $price = $lineItem->getPrice();

        if ($price === null) {
            return;
        }

        $quantity = $lineItem->getQuantity();

        if ($quantity === 0) {
            throw DocumentV2Exception::invalidOrderData(
                $order->getId(),
                'lineItem.quantity',
                \sprintf('Line item "%s" has zero quantity.', $lineItem->getIdentifier()),
            );
        }

        if ($quantity < 0) {
            throw DocumentV2Exception::invalidOrderData(
                $order->getId(),
                'lineItem.quantity',
                \sprintf('Line item "%s" has negative quantity.', $lineItem->getIdentifier()),
            );
        }

        $position = $parentPosition . $lineItem->getPosition();
        $taxes = $price->getCalculatedTaxes();

        if ($taxes->count() <= 1) {
            $items[] = self::createCreditLineItem($lineItem, $position, $quantity, $taxes->first(), $price, $isGross);

            return;
        }

        $row = 1;

        foreach ($taxes as $tax) {
            $items[] = self::createCreditLineItem($lineItem, $position . '-' . $row, $quantity, $tax, $price, $isGross);
            ++$row;
        }
    }

    private static function createCreditLineItem(
        OrderLineItemEntity $lineItem,
        string $lineId,
        int $quantity,
        ?CalculatedTax $tax,
        CalculatedPrice $price,
        bool $isGross,
    ): self {
        $lineTotal = NetAmount::fromTax($tax, $price, $isGross);
        $taxRate = $tax?->getTaxRate() ?? 0.0;
        $product = $lineItem->getProduct();

        return new self(
            lineId: $lineId,
            productNumber: $product?->getProductNumber(),
            ean: $product?->getEan(),
            name: $lineItem->getLabel(),
            quantity: $quantity,
            basisQuantity: self::PRICE_BASIS_QUANTITY,
            unitCode: UnitCode::PIECE,
            netUnitPrice: $lineTotal / $quantity,
            lineTotal: $lineTotal,
            taxCategory: TaxCategory::fromRate($taxRate),
            taxRate: $taxRate,
        );
    }
}
