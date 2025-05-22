<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
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
        $order->setAmountTotal(0.0);
        $order->setAmountNet(0.0);

        (new ZugferdDocument(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3)))->getContent($order);
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
    public function testDifferentTaxCalculationType(string $calculationType, bool $gross, array $expected): void
    {
        $position = 0;
        $order = new OrderEntity();
        $order->setTaxCalculationType($calculationType);
        $order->setItemRounding(new CashRoundingConfig(2, .01, false));
        $order->setAmountTotal(123.4);
        $order->setAmountNet(100);

        $document = new ZugferdDocumentMock(ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), $gross);

        $document
            ->withProductLineItem($this->createOrderLineItem(1.87, 19.0, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem(4.5, 19.0, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem(2.42, 19.0, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem(4.74, 19.0, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem(1.93, 19.0, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem(2.6, 19.0, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem(4.21, 19.0, ++$position), '')
            ->withProductLineItem($this->createOrderLineItem(10.7, 7.0, ++$position), '');

        $document
            ->withDiscountItem($this->createOrderLineItem(1.4, 19.0))
            ->withDiscountItem($this->createOrderLineItem(1.34, 19.0))
            ->withDiscountItem($this->createOrderLineItem(5.2, 19.0))
            ->withDiscountItem($this->createOrderLineItem(2.4, 19.0))
            ->withDiscountItem($this->createOrderLineItem(0.7, 19.0))
            ->withDiscountItem($this->createOrderLineItem(0.2, 7.0));

        $document->withDelivery(new OrderDeliveryCollection([
            $this->createOrderDeliveryItem(20.33, 19.0),
            $this->createOrderDeliveryItem(15.44, 19.0),
            $this->createOrderDeliveryItem(10.28, 19.0),
            $this->createOrderDeliveryItem(5.0, 7.0),
        ]));

        $this->validateDocument($document->getDomContent($order), $expected);
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
                ['28.70', '43.36', '9.48', '100.00', '23.40', '123.40', '123.40'],
            ],
            'Gross vertical' => [
                SalesChannelDefinition::CALCULATION_TYPE_VERTICAL,
                true,
                ['28.71', '43.37', '9.46', '100.00', '23.40', '123.40', '123.40'],
            ],
            'Net horizontal' => [
                SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL,
                false,
                ['32.97', '51.05', '11.24', '100.00', '23.40', '123.40', '123.40'],
            ],
            'Net vertical' => [
                SalesChannelDefinition::CALCULATION_TYPE_VERTICAL,
                false,
                ['32.97', '51.05', '11.24', '100.00', '23.40', '123.40', '123.40'],
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
        $grandTotalAmount = $summary->getElementsByTagName('GrandTotalAmount');
        $duePayableAmount = $summary->getElementsByTagName('DuePayableAmount');

        static::assertSame(1, $lineTotalAmount->length);
        static::assertSame(1, $chargeTotalAmount->length);
        static::assertSame(1, $allowanceTotalAmount->length);
        static::assertSame(1, $taxBasisTotalAmount->length);
        static::assertSame(1, $taxTotalAmount->length);
        static::assertSame(1, $grandTotalAmount->length);
        static::assertSame(1, $duePayableAmount->length);

        static::assertSame($expected[0], $lineTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[1], $chargeTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[2], $allowanceTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[3], $taxBasisTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[4], $taxTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[5], $grandTotalAmount->item(0)?->nodeValue);
        static::assertSame($expected[6], $duePayableAmount->item(0)?->nodeValue);
    }

    private function createOrderLineItem(float $gross, float $taxRate, ?int $position = null): OrderLineItemEntity
    {
        $tax = new CalculatedTax(
            round($gross - $gross / (1 + $taxRate / 100), 2),
            $taxRate,
            $gross
        );

        $item = new OrderLineItemEntity();
        $item->setId(Uuid::randomHex());
        $item->setLabel('Product ' . $item->getId());
        $item->setQuantity(1);
        $item->setPrice(new CalculatedPrice(
            $gross,
            $gross,
            new CalculatedTaxCollection([$tax]),
            new TaxRuleCollection(),
        ));

        if ($position !== null) {
            $item->setPosition($position);
        }

        return $item;
    }

    private function createOrderDeliveryItem(float $gross, float $taxRate): OrderDeliveryEntity
    {
        $tax = new CalculatedTax(
            round($gross - $gross / (1 + $taxRate / 100), 2),
            $taxRate,
            $gross
        );

        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setShippingCosts(new CalculatedPrice(
            $gross,
            $gross,
            new CalculatedTaxCollection([$tax]),
            new TaxRuleCollection(),
        ));

        return $delivery;
    }
}
