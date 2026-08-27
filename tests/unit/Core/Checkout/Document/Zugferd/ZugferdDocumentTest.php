<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
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
use Shopware\Core\Defaults;
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

    public function testWithUnsetPrice(): void
    {
        static::expectExceptionObject(DocumentException::generationError('Price can\'t be null'));

        $lineItem = new OrderLineItemEntity();
        $lineItem->setLabel('Test Item');

        (new ZugferdDocument(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3)))
            ->withProductLineItem($lineItem, '');
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testWithNegativePrice(bool $allowNegative): void
    {
        if ($allowNegative) {
            static::expectNotToPerformAssertions();
        } else {
            static::expectExceptionObject(DocumentException::generationError('Price can\'t be negative'));
        }

        $lineItem = new OrderLineItemEntity();
        $lineItem->setLabel('Test Item');
        $lineItem->setUnitPrice(-10);
        $lineItem->setTotalPrice(-10);
        $lineItem->setPosition(1);
        $lineItem->setQuantity(1);

        $lineItem->setPrice(new CalculatedPrice(
            $lineItem->getUnitPrice(),
            $lineItem->getTotalPrice(),
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        ));

        $zugferdDocument = new ZugferdDocument(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3));

        if ($allowNegative) {
            $zugferdDocument->allowNegativeProductLineItems();
        }

        $zugferdDocument->withProductLineItem($lineItem, '');
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

        $document->withInvoiceReference('1001', new \DateTimeImmutable('2024-01-01'));

        $document
            ->withDocumentInformation(
                (new \DateTime('2024-01-03'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                '1002',
                'EUR',
                ZugferdInvoiceType::CORRECTION,
            )
            ->withDocumentSupplyChainEvent(new \DateTime('2024-01-02'));

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

    public function testWithDocumentInformation(): void
    {
        $document = new ZugferdDocumentMock(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), true);
        $document->withDocumentInformation('2024-01-03', '1002', 'EUR', ZugferdInvoiceType::CORRECTION);

        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setAmountTotal(0.0);
        $order->setAmountNet(0.0);
        $order->setItemRounding(new CashRoundingConfig(2, .01, false));
        $order->setTotalRounding(new CashRoundingConfig(2, .01, false));

        $calculator = new AmountCalculator(new CashRounding(), new PercentageTaxRuleBuilder(), new TaxCalculator());
        $dom = $document->getDomContent($order, $calculator);

        $general = $dom->getElementsByTagName('ExchangedDocument')->item(0);

        static::assertNotNull($general);
        static::assertSame('1002', $general->getElementsByTagName('ID')->item(0)?->nodeValue);
        static::assertSame(ZugferdInvoiceType::CORRECTION, $general->getElementsByTagName('TypeCode')->item(0)?->nodeValue);
        static::assertSame('20240103', \trim($general->getElementsByTagName('IssueDateTime')->item(0)->nodeValue ?? ''));
    }

    public function testWithDocumentSupplyChainEvent(): void
    {
        $document = new ZugferdDocumentMock(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), true);
        $document->withDocumentSupplyChainEvent(new \DateTime('2024-01-02'));

        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setAmountTotal(0.0);
        $order->setAmountNet(0.0);
        $order->setItemRounding(new CashRoundingConfig(2, .01, false));
        $order->setTotalRounding(new CashRoundingConfig(2, .01, false));

        $calculator = new AmountCalculator(new CashRounding(), new PercentageTaxRuleBuilder(), new TaxCalculator());
        $dom = $document->getDomContent($order, $calculator);

        $occurrenceDateTime = $dom->getElementsByTagName('ActualDeliverySupplyChainEvent')->item(0)
            ?->getElementsByTagName('OccurrenceDateTime')->item(0);

        static::assertNotNull($occurrenceDateTime);
        static::assertSame('20240102', \trim($occurrenceDateTime->getElementsByTagName('DateTimeString')->item(0)->nodeValue ?? ''));
    }

    public function testWithDeliverySkipsZeroAmountAllowanceCharge(): void
    {
        $dom = $this->createDeliveryDocumentDom(0.0, ZugferdInvoiceType::INVOICE);

        static::assertSame(0, $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->length);
    }

    public function testWithDeliveryAddsChargeNodeForPositiveInvoiceShippingCosts(): void
    {
        $dom = $this->createDeliveryDocumentDom(10.0, ZugferdInvoiceType::INVOICE);

        static::assertSame(1, $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->length);

        $allowanceCharge = $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->item(0);
        static::assertNotNull($allowanceCharge);
        static::assertSame('true', $allowanceCharge->getElementsByTagName('Indicator')->item(0)?->nodeValue);
        static::assertSame('10.00', $allowanceCharge->getElementsByTagName('ActualAmount')->item(0)?->nodeValue);
        static::assertSame('DL', $allowanceCharge->getElementsByTagName('ReasonCode')->item(0)?->nodeValue);
        static::assertSame('Delivery', $allowanceCharge->getElementsByTagName('Reason')->item(0)?->nodeValue);
    }

    public function testWithDeliveryAddsChargeNodeForPositiveCorrectionShippingCosts(): void
    {
        $dom = $this->createDeliveryDocumentDom(10.0, ZugferdInvoiceType::CORRECTION);

        static::assertSame(1, $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->length);

        $allowanceCharge = $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->item(0);
        static::assertNotNull($allowanceCharge);
        static::assertSame('true', $allowanceCharge->getElementsByTagName('Indicator')->item(0)?->nodeValue);
        static::assertSame('10.00', $allowanceCharge->getElementsByTagName('ActualAmount')->item(0)?->nodeValue);
        static::assertSame('DAM', $allowanceCharge->getElementsByTagName('ReasonCode')->item(0)?->nodeValue);
        static::assertSame('Return handling', $allowanceCharge->getElementsByTagName('Reason')->item(0)?->nodeValue);
    }

    public function testWithDeliveryAddsAllowanceNodeForNegativeCorrectionShippingCosts(): void
    {
        $dom = $this->createDeliveryDocumentDom(-10.0, ZugferdInvoiceType::CORRECTION);

        static::assertSame(1, $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->length);

        $allowanceCharge = $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->item(0);
        static::assertNotNull($allowanceCharge);
        static::assertSame('false', $allowanceCharge->getElementsByTagName('Indicator')->item(0)?->nodeValue);
        static::assertSame('10.00', $allowanceCharge->getElementsByTagName('ActualAmount')->item(0)?->nodeValue);
        static::assertSame('95', $allowanceCharge->getElementsByTagName('ReasonCode')->item(0)?->nodeValue);
        static::assertSame('Delivery refund', $allowanceCharge->getElementsByTagName('Reason')->item(0)?->nodeValue);
    }

    public function testWithDiscountItemAddsAllowanceForTaxFreeOrder(): void
    {
        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_FREE);
        $order->setAmountTotal(0.0);
        $order->setAmountNet(0.0);
        $order->setItemRounding(new CashRoundingConfig(2, .01, false));
        $order->setTotalRounding(new CashRoundingConfig(2, .01, false));

        $discount = new OrderLineItemEntity();
        $discount->setId(Uuid::randomHex());
        $discount->setLabel('Summer sale');
        $discount->setQuantity(1);
        $discount->setPosition(1);
        $discount->setPayload(['value' => 10.0]);
        $discount->setUnitPrice(-10.0);
        $discount->setTotalPrice(-10.0);
        $discount->setPrice(new CalculatedPrice(
            -10.0,
            -10.0,
            new CalculatedTaxCollection([]),
            new TaxRuleCollection(),
        ));

        $document = new ZugferdDocumentMock(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), false);
        $document->withDiscountItem($discount);

        $calculator = new AmountCalculator(new CashRounding(), new PercentageTaxRuleBuilder(), new TaxCalculator());
        $dom = $document->getDomContent($order, $calculator);

        static::assertSame(1, $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->length);

        $allowanceCharge = $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->item(0);
        static::assertNotNull($allowanceCharge);

        static::assertSame('false', $allowanceCharge->getElementsByTagName('Indicator')->item(0)?->nodeValue);
        static::assertSame('10.00', $allowanceCharge->getElementsByTagName('ActualAmount')->item(0)?->nodeValue);
        static::assertSame('95', $allowanceCharge->getElementsByTagName('ReasonCode')->item(0)?->nodeValue);
        static::assertSame('Summer sale', $allowanceCharge->getElementsByTagName('Reason')->item(0)?->nodeValue);
        static::assertSame('Z', $allowanceCharge->getElementsByTagName('CategoryCode')->item(0)?->nodeValue);
        static::assertSame('10.00', $dom->getElementsByTagName('AllowanceTotalAmount')->item(0)?->nodeValue);

        $this->assertAllowanceChargeTotalsReconcile($dom);
    }

    public function testWithDiscountItemSkipsZeroAmountAllowanceCharge(): void
    {
        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_FREE);
        $order->setAmountTotal(0.0);
        $order->setAmountNet(0.0);
        $order->setItemRounding(new CashRoundingConfig(2, .01, false));
        $order->setTotalRounding(new CashRoundingConfig(2, .01, false));

        $discount = new OrderLineItemEntity();
        $discount->setId(Uuid::randomHex());
        $discount->setLabel('Summer sale');
        $discount->setQuantity(1);
        $discount->setPosition(1);
        $discount->setPayload(['value' => 0.0]);
        $discount->setUnitPrice(0.0);
        $discount->setTotalPrice(0.0);
        $discount->setPrice(new CalculatedPrice(
            0.0,
            0.0,
            new CalculatedTaxCollection([]),
            new TaxRuleCollection(),
        ));

        $document = new ZugferdDocumentMock(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), false);
        $document->withDiscountItem($discount);

        $calculator = new AmountCalculator(new CashRounding(), new PercentageTaxRuleBuilder(), new TaxCalculator());
        $dom = $document->getDomContent($order, $calculator);

        static::assertSame(0, $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->length);
    }

    public function testWithDeliveryAddsChargeForTaxFreeShippingCosts(): void
    {
        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_FREE);
        $order->setAmountTotal(10.0);
        $order->setAmountNet(10.0);
        $order->setItemRounding(new CashRoundingConfig(2, .01, false));
        $order->setTotalRounding(new CashRoundingConfig(2, .01, false));

        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setUniqueIdentifier($delivery->getId());
        $delivery->setShippingCosts(new CalculatedPrice(
            10.0,
            10.0,
            new CalculatedTaxCollection([]),
            new TaxRuleCollection(),
        ));

        $document = new ZugferdDocumentMock(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), false);
        $document->withDocumentInformation('2024-01-03', '1002', 'EUR', ZugferdInvoiceType::INVOICE);
        $document->withDelivery(new OrderDeliveryCollection([$delivery]));

        $calculator = new AmountCalculator(new CashRounding(), new PercentageTaxRuleBuilder(), new TaxCalculator());
        $dom = $document->getDomContent($order, $calculator);

        static::assertSame(1, $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->length);

        $allowanceCharge = $dom->getElementsByTagName('SpecifiedTradeAllowanceCharge')->item(0);
        static::assertNotNull($allowanceCharge);

        static::assertSame('true', $allowanceCharge->getElementsByTagName('Indicator')->item(0)?->nodeValue);
        static::assertSame('10.00', $allowanceCharge->getElementsByTagName('ActualAmount')->item(0)?->nodeValue);
        static::assertSame('DL', $allowanceCharge->getElementsByTagName('ReasonCode')->item(0)?->nodeValue);
        static::assertSame('Delivery', $allowanceCharge->getElementsByTagName('Reason')->item(0)?->nodeValue);
        static::assertSame('Z', $allowanceCharge->getElementsByTagName('CategoryCode')->item(0)?->nodeValue);
        static::assertSame('10.00', $dom->getElementsByTagName('ChargeTotalAmount')->item(0)?->nodeValue);

        $this->assertAllowanceChargeTotalsReconcile($dom);
    }

    public function testEmptyCalculatedTaxes(): void
    {
        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setAmountTotal(100.0);
        $order->setAmountNet(100);
        $order->setItemRounding(new CashRoundingConfig(2, .01, false));
        $order->setTotalRounding(new CashRoundingConfig(2, .01, false));

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setLabel('Product ' . $lineItem->getId());
        $lineItem->setQuantity(1);
        $lineItem->setPosition(1);
        $lineItem->setPrice(new CalculatedPrice(
            100.0,
            100.0,
            new CalculatedTaxCollection([]),
            new TaxRuleCollection(),
        ));

        $document = new ZugferdDocumentMock(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), true);
        $document->withProductLineItem($lineItem, '');
        $document->withPaidAmount(100.0);
        $document->withInvoiceReference('1001', new \DateTimeImmutable('2024-01-01'));
        $document
            ->withDocumentInformation(
                (new \DateTime('2024-01-03'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                '1002',
                'EUR',
                ZugferdInvoiceType::CORRECTION,
            )
            ->withDocumentSupplyChainEvent(new \DateTime('2024-01-02'));

        $calculator = new AmountCalculator(
            new CashRounding(),
            new PercentageTaxRuleBuilder(),
            new TaxCalculator()
        );

        $this->validateDocument($document->getDomContent($order, $calculator), ['100.00', '0.00', '0.00', '100.00', '0.00', '100.00', '100.00', '0.00']);
    }

    private function createDeliveryDocumentDom(float $shippingTotal, string $documentType): \DOMDocument
    {
        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setAmountTotal(abs($shippingTotal));
        $order->setAmountNet(abs($shippingTotal));
        $order->setItemRounding(new CashRoundingConfig(2, .01, false));
        $order->setTotalRounding(new CashRoundingConfig(2, .01, false));

        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setUniqueIdentifier($delivery->getId());
        $delivery->setShippingCosts(new CalculatedPrice(
            $shippingTotal,
            $shippingTotal,
            new CalculatedTaxCollection([new CalculatedTax(0.0, 19.0, $shippingTotal)]),
            new TaxRuleCollection(),
        ));

        $document = new ZugferdDocumentMock(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), true);
        $document->withDocumentInformation('2024-01-03', '1002', 'EUR', $documentType);
        $document->withDelivery(new OrderDeliveryCollection([$delivery]));

        $calculator = new AmountCalculator(
            new CashRounding(),
            new PercentageTaxRuleBuilder(),
            new TaxCalculator()
        );

        return $document->getDomContent($order, $calculator);
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

        $invoiceReference = $document->getElementsByTagName('InvoiceReferencedDocument')->item(0);
        static::assertNotNull($invoiceReference);

        static::assertSame('1001', $invoiceReference->getElementsByTagName('IssuerAssignedID')->item(0)?->nodeValue);
        static::assertSame('20240101', $invoiceReference->getElementsByTagName('DateTimeString')->item(0)?->nodeValue);

        $general = $document->getElementsByTagName('ExchangedDocument')->item(0);
        static::assertNotNull($general);

        static::assertSame('1002', $general->getElementsByTagName('ID')->item(0)?->nodeValue);
        static::assertSame(ZugferdInvoiceType::CORRECTION, $general->getElementsByTagName('TypeCode')->item(0)?->nodeValue);
        static::assertSame('20240103', \trim($general->getElementsByTagName('IssueDateTime')->item(0)->nodeValue ?? ''));
    }

    private function assertAllowanceChargeTotalsReconcile(\DOMDocument $document): void
    {
        $settlement = $document->getElementsByTagName('ApplicableHeaderTradeSettlement')->item(0);
        static::assertNotNull($settlement);

        $chargeSum = 0.0;
        $allowanceSum = 0.0;

        foreach ($settlement->getElementsByTagName('SpecifiedTradeAllowanceCharge') as $group) {
            $actualAmount = (float) $group->getElementsByTagName('ActualAmount')->item(0)?->nodeValue;

            if ($group->getElementsByTagName('Indicator')->item(0)?->nodeValue === 'true') {
                $chargeSum += $actualAmount;
            } else {
                $allowanceSum += $actualAmount;
            }
        }

        $summary = $settlement->getElementsByTagName('SpecifiedTradeSettlementHeaderMonetarySummation')->item(0);
        static::assertNotNull($summary);

        static::assertSame(
            $summary->getElementsByTagName('AllowanceTotalAmount')->item(0)?->nodeValue,
            \number_format($allowanceSum, 2, '.', '')
        );

        static::assertSame(
            $summary->getElementsByTagName('ChargeTotalAmount')->item(0)?->nodeValue,
            \number_format($chargeSum, 2, '.', '')
        );
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
