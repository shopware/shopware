<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\View;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\DocumentV2\Template\Calculation\NetAmount;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TaxCategory;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Precomputed view of a single header-tax row.
 *
 * One entry is emitted per distinct tax rate on the order. The precondition
 * (no duplicate rates in {@see CalculatedTaxCollection}) is enforced by the collection itself,
 * which keys entries by tax rate.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class TaxBreakdownView
{
    public function __construct(
        public float $calculatedAmount,
        public float $basisAmount,
        public TaxCategory $taxCategory,
        public float $taxRate,
    ) {
    }

    /**
     * @return list<self>
     */
    public static function listFromOrder(OrderEntity $order): array
    {
        $price = $order->getPrice();

        if ($price->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            return [new self(
                calculatedAmount: 0.0,
                basisAmount: $price->getNetPrice(),
                taxCategory: TaxCategory::ZERO_RATED,
                taxRate: 0.0,
            )];
        }

        $isGross = NetAmount::isOrderGross($order);

        $views = [];

        if ($price->getCalculatedTaxes()->count() === 0) {
            return [new self(
                calculatedAmount: 0.0,
                basisAmount: $price->getNetPrice(),
                taxCategory: TaxCategory::ZERO_RATED,
                taxRate: 0.0,
            )];
        }

        foreach ($price->getCalculatedTaxes() as $tax) {
            $views[] = new self(
                calculatedAmount: $tax->getTax(),
                basisAmount: NetAmount::fromTax($tax, null, $isGross),
                taxCategory: TaxCategory::fromRate($tax->getTaxRate()),
                taxRate: $tax->getTaxRate(),
            );
        }

        return $views;
    }
}
