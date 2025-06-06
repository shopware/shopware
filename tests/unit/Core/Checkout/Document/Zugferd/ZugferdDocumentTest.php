<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdDocument::class)]
class ZugferdDocumentTest extends TestCase
{
    public function testViolations(): void
    {
        $this->expectException(DocumentException::class);
        $this->expectExceptionMessageMatches('/Unable to generate document. ([0-9]+) violation\(s\) found/');

        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setAmountTotal(0.0);
        $order->setAmountNet(0.0);
        $order->setItemRounding(new CashRoundingConfig(2, .01, false));
        $order->setTotalRounding(new CashRoundingConfig(2, .01, false));

        (new ZugferdDocument(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3)))
            ->getContent(
                $order,
                new AmountCalculator(
                    new CashRounding(),
                    new PercentageTaxRuleBuilder(),
                    new TaxCalculator()
                )
            );
    }

    public function testWithNegativePrice(): void
    {
        $this->expectException(DocumentException::class);
        $this->expectExceptionMessage('Price can\'t be negative or null: Test Item');

        $lineItem = new OrderLineItemEntity();
        $lineItem->setLabel('Test Item');
        $lineItem->setUnitPrice(-10);
        $lineItem->setTotalPrice(-10);

        $lineItem->setPrice(new CalculatedPrice(
            $lineItem->getUnitPrice(),
            $lineItem->getTotalPrice(),
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        ));

        (new ZugferdDocument(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3)))->withProductLineItem($lineItem, '');
    }

    /**
     * @param string[] $expected
     */
    #[DataProvider('dataProviderDifferentType')]
    public function testDifferentTaxCalculationType(string $calculationType, bool $isGross, array $expected): void
    {
        $position = 0;
        $order = new OrderEntity();
        $order->setTaxCalculationType($calculationType);
        $order->setItemRounding(new CashRoundingConfig(2, .01, false));
        $order->setTotalRounding(new CashRoundingConfig(2, .01, false));
        $order->setTaxStatus($isGross ? CartPrice::TAX_STATE_GROSS : CartPrice::TAX_STATE_NET);

        $document = new ZugferdDocumentMock(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), $isGross);

        $lineItemGross = [1.87, 4.5, 2.42, 4.74, 1.93, 2.6, 4.21, 10.7];
        $document
            ->withProductLineItem($this->createOrderLineItem($lineItemGross[0], 19.0, $isGross, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem($lineItemGross[1], 19.0, $isGross, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem($lineItemGross[2], 19.0, $isGross, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem($lineItemGross[3], 19.0, $isGross, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem($lineItemGross[4], 19.0, $isGross, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem($lineItemGross[5], 19.0, $isGross, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem($lineItemGross[6], 19.0, $isGross, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem($lineItemGross[7], 7.0, $isGross, ++$position), '');

        $discountGross = [-1.4, -1.34, 5.2, 2.4, -0.7, -0.2];
        $document
            ->withDiscountItem($this->createOrderLineItem($discountGross[0], 19.0, $isGross))
            ->withDiscountItem($this->createOrderLineItem($discountGross[1], 19.0, $isGross))
            ->withDiscountItem($this->createOrderLineItem($discountGross[2], 19.0, $isGross))
            ->withDiscountItem($this->createOrderLineItem($discountGross[3], 19.0, $isGross))
            ->withDiscountItem($this->createOrderLineItem($discountGross[4], 19.0, $isGross))
            ->withDiscountItem($this->createOrderLineItem($discountGross[5], 7.0, $isGross));

        $deliveryGross = [20.33, 15.44, 10.28, 5.0];
        $document->withDelivery(new OrderDeliveryCollection([
            $this->createOrderDeliveryItem($deliveryGross[0], 19.0, $isGross),
            $this->createOrderDeliveryItem($deliveryGross[1], 19.0, $isGross),
            $this->createOrderDeliveryItem($deliveryGross[2], 19.0, $isGross),
            $this->createOrderDeliveryItem($deliveryGross[3], 7.0, $isGross),
        ]));

        if ($isGross) {
            $order->setAmountTotal(round(array_sum($lineItemGross) + array_sum($discountGross) + array_sum($deliveryGross), 2));
            $order->setAmountNet((float) $expected[3]);
        } else {
            $order->setAmountTotal((float) $expected[5]);
            $order->setAmountNet(round(array_sum($lineItemGross) + array_sum($discountGross) + array_sum($deliveryGross), 2));
        }

        $document->withPaidAmount((float) $expected[6]);

        $calculator = new AmountCalculator(
            new CashRounding(),
            new PercentageTaxRuleBuilder(),
            new TaxCalculator()
        );

        $this->validateDocument($document->getDomContent($order, $calculator), $expected);
    }

    /**
     * @return array<array{0: string, 1: bool, 2: string[]}>
     */
    public static function dataProviderDifferentType(): array
    {
        return [
            'Gross horizontal' => [
                SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL,
                true,
                ['28.70', '49.75', '3.09', '75.36', '12.62', '87.98', '45.26', '42.72'],
            ],
            'Gross vertical' => [
                SalesChannelDefinition::CALCULATION_TYPE_VERTICAL,
                true,
                ['28.71', '49.75', '3.08', '75.38', '12.60', '87.98', '45.26', '42.72'],
            ],
            'Net horizontal' => [
                SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL,
                false,
                ['32.97', '58.65', '3.64', '87.98', '14.87', '102.85', '45.26', '57.59'],
            ],
            'Net vertical' => [
                SalesChannelDefinition::CALCULATION_TYPE_VERTICAL,
                false,
                ['32.97', '58.65', '3.64', '87.98', '14.87', '102.85', '45.26', '57.59'],
            ],
        ];
    }

    /**
     * @param string[] $expected
     */
    private function validateDocument(\DOMDocument $document, array $expected): void
    {
        $summary = $document->getElementsByTagName('SpecifiedTradeSettlementHeaderMonetarySummation')->item(0);

        static::assertNotNull($summary);

        $lineTotalAmount = $summary->getElementsByTagName('LineTotalAmount');
        $chargeTotalAmount = $summary->getElementsByTagName('ChargeTotalAmount');
        $allowanceTotalAmount = $summary->getElementsByTagName('AllowanceTotalAmount');
        $taxBasisTotalAmount = $summary->getElementsByTagName('TaxBasisTotalAmount');
        $taxTotalAmount = $summary->getElementsByTagName('TaxTotalAmount');
        $roundingAmount = $summary->getElementsByTagName('RoundingAmount');
        $grandTotalAmount = $summary->getElementsByTagName('GrandTotalAmount');
        $totalPrepaidAmount = $summary->getElementsByTagName('TotalPrepaidAmount');
        $duePayableAmount = $summary->getElementsByTagName('DuePayableAmount');

        static::assertSame(1, $lineTotalAmount->length);
        static::assertSame(1, $chargeTotalAmount->length);
        static::assertSame(1, $allowanceTotalAmount->length);
        static::assertSame(1, $taxBasisTotalAmount->length);
        static::assertSame(1, $taxTotalAmount->length);
        static::assertSame(1, $roundingAmount->length);
        static::assertSame(1, $grandTotalAmount->length);
        static::assertSame(1, $totalPrepaidAmount->length);
        static::assertSame(1, $duePayableAmount->length);

        static::assertSame($expected[0], $lineTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[1], $chargeTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[2], $allowanceTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[3], $taxBasisTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[4], $taxTotalAmount->item(0)?->nodeValue);
        static::assertSame('0.00', $roundingAmount->item(0)?->nodeValue);
        static::assertSame($expected[5], $grandTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[6], $totalPrepaidAmount->item(0)?->nodeValue);
        static::assertSame($expected[7], $duePayableAmount->item(0)?->nodeValue);

        $totalNet = (float) $lineTotalAmount->item(0)->nodeValue + (float) $chargeTotalAmount->item(0)->nodeValue - (float) $allowanceTotalAmount->item(0)->nodeValue;
        $totalGross = (float) $taxBasisTotalAmount->item(0)->nodeValue + (float) $taxTotalAmount->item(0)->nodeValue;
        $paidWithDuePayableAmount = (float) $totalPrepaidAmount->item(0)->nodeValue + (float) $duePayableAmount->item(0)->nodeValue;

        static::assertSame((float) $taxBasisTotalAmount->item(0)->nodeValue, round($totalNet, 2));
        static::assertSame((float) $grandTotalAmount->item(0)->nodeValue, round($totalGross, 2));
        static::assertSame((float) $grandTotalAmount->item(0)->nodeValue, round($paidWithDuePayableAmount, 2));
    }

    private function createOrderLineItem(float $price, float $taxRate, bool $isGross, ?int $position = null): OrderLineItemEntity
    {
        // multiplier, to minimize rounding errors
        $calculationPrice = $price * 100;
        $rate = $isGross ? $calculationPrice - $calculationPrice / (1 + $taxRate / 100) : ($calculationPrice * (1 + $taxRate / 100) - $calculationPrice);

        $tax = new CalculatedTax(
            round($rate / 100, 2),
            $taxRate,
            $price
        );

        $item = new OrderLineItemEntity();
        $item->setId(Uuid::randomHex());
        $item->setLabel('Product ' . $item->getId());
        $item->setQuantity(1);
        $item->setPrice(new CalculatedPrice(
            $price,
            $price,
            new CalculatedTaxCollection([$tax]),
            new TaxRuleCollection(),
        ));

        if ($position !== null) {
            $item->setPosition($position);
        }

        return $item;
    }

    private function createOrderDeliveryItem(float $price, float $taxRate, bool $isGross): OrderDeliveryEntity
    {
        $rate = $isGross ? $price - $price / (1 + $taxRate / 100) : ($price * (1 + $taxRate / 100) - $price);
        $tax = new CalculatedTax(
            round($rate, 2),
            $taxRate,
            $price
        );

        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setShippingCosts(new CalculatedPrice(
            $price,
            $price,
            new CalculatedTaxCollection([$tax]),
            new TaxRuleCollection(),
        ));

        return $delivery;
    }
}
