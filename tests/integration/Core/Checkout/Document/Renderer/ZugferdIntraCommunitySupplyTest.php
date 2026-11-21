<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use horstoeko\zugferd\ZugferdDocumentPdfReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Extension\ResolveDispatchCountryExtension;
use Shopware\Core\Checkout\Cart\Extension\ResolveVatIdsExtension;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCancellationInvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\IntraCommunitySupplyTrait;

/**
 * The ZUGFeRD XML of the legacy document pipeline states an intra-community delivery with VAT category K.
 *
 * @internal
 */
#[Package('after-sales')]
class ZugferdIntraCommunitySupplyTest extends TestCase
{
    use IntraCommunitySupplyTrait;
    use SnapshotTesting;

    protected function setUp(): void
    {
        $this->setUpIntraCommunitySupply();
    }

    public function testTheInvoiceGivesTheLineTheShippingChargeAndTheAllowanceCategoryK(): void
    {
        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeOrder());

        foreach ([
            '//ram:IncludedSupplyChainTradeLineItem//ram:ApplicableTradeTax',
            '//ram:SpecifiedTradeAllowanceCharge[ram:ChargeIndicator/udt:Indicator="true"]/ram:CategoryTradeTax',
            '//ram:SpecifiedTradeAllowanceCharge[ram:ChargeIndicator/udt:Indicator="false"]/ram:CategoryTradeTax',
        ] as $position) {
            static::assertSame(['K'], self::values($xml, $position . '/ram:CategoryCode'), $position);
            static::assertSame(['0.00'], self::values($xml, $position . '/ram:RateApplicablePercent'), $position);
        }
    }

    public function testTheInvoiceHasOneExemptVatBreakdown(): void
    {
        $this->assertExemptBreakdown($this->render(ZugferdRenderer::TYPE, $this->placeOrder()), '100.00');
    }

    public function testTheInvoiceNamesTheBuyerVatIdNextToTheSellerVatIdAndTaxNumber(): void
    {
        $this->assertVatIds($this->render(ZugferdRenderer::TYPE, $this->placeOrder()));
    }

    public function testTheInvoiceNamesTheDeliveryAddressAndTheDeliveryDate(): void
    {
        $orderId = $this->placeOrder();

        $this->assertDeliverTo($this->render(ZugferdRenderer::TYPE, $orderId), $orderId);
    }

    public function testTheInvoiceKeepsItsAmounts(): void
    {
        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeOrder());

        $summation = '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/';
        static::assertSame(['100.00'], self::values($xml, $summation . 'ram:LineTotalAmount'));
        static::assertSame(['10.00'], self::values($xml, $summation . 'ram:ChargeTotalAmount'));
        static::assertSame(['10.00'], self::values($xml, $summation . 'ram:AllowanceTotalAmount'));
        static::assertSame(['100.00'], self::values($xml, $summation . 'ram:GrandTotalAmount'));
    }

    public function testTheInvoicePassesTheXsdValidation(): void
    {
        // render() already fails on the e-invoice violation the library's object validation raises
        $this->assertXsdValid($this->render(ZugferdRenderer::TYPE, $this->placeOrder()));
    }

    public function testTheCancellationInvoiceStatesTheIntraCommunitySupplyWithItsAmounts(): void
    {
        $orderId = $this->placeOrder();

        $xml = $this->render(ZugferdCancellationInvoiceRenderer::TYPE, $orderId, $this->createInvoice($orderId));

        $this->assertIntraCommunitySupply($xml, $orderId, '-100.00');
        $this->assertXsdValid($xml);
        $summation = '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/';
        static::assertSame(['-100.00'], self::values($xml, $summation . 'ram:LineTotalAmount'));
        static::assertSame(['10.00'], self::values($xml, $summation . 'ram:ChargeTotalAmount'));
        static::assertSame(['10.00'], self::values($xml, $summation . 'ram:AllowanceTotalAmount'));
        static::assertSame(['-100.00'], self::values($xml, $summation . 'ram:GrandTotalAmount'));
    }

    public function testTheCreditNoteStatesTheIntraCommunitySupplyOnTheCreditLine(): void
    {
        $orderId = $this->placeOrder();
        $invoiceId = $this->createInvoice($orderId);
        $this->addCreditItemToOrder($orderId);

        $xml = $this->render(ZugferdCreditNoteRenderer::TYPE, $orderId, $invoiceId);

        static::assertSame(['credit'], self::values($xml, '//ram:IncludedSupplyChainTradeLineItem//ram:Name'));
        $this->assertIntraCommunitySupply($xml, $orderId, '10.00');
        $this->assertXsdValid($xml);
    }

    public function testTheEmbeddedInvoiceCarriesTheIntraCommunitySupply(): void
    {
        $pdf = $this->render(ZugferdEmbeddedRenderer::TYPE, $this->placeOrder(), fileType: FileTypes::PDF);

        $xml = ZugferdDocumentPdfReader::getXmlFromContent($pdf);
        $this->assertCategory($xml, 'K', '0.00');
        static::assertSame(['VATEX-EU-IC'], self::values($xml, self::BREAKDOWN . '/ram:ExemptionReasonCode'));
        static::assertSame([self::VAT_ID], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    public function testTheIntraCommunityDeliveryLabelDoesNotChangeTheXml(): void
    {
        $orderId = $this->placeOrder();

        $xml = $this->render(ZugferdRenderer::TYPE, $orderId, config: ['displayAdditionalNoteDelivery' => true]);

        $this->assertIntraCommunitySupply($xml, $orderId, '100.00');
    }

    /**
     * @param array{billingCountry?: string, shippingCountry?: string, vatIds?: list<string>, accountType?: string, withPromotion?: bool} $order
     */
    #[DataProvider('ordersWithoutIntraCommunitySupply')]
    public function testAnotherOrderKeepsTheXmlItHasToday(array $order, string $category, ?string $frenchVatIdPattern = null): void
    {
        if ($frenchVatIdPattern !== null) {
            $this->changeFrenchVatIdCheck(true, $frenchVatIdPattern);
        }

        $this->assertNoIntraCommunitySupply($this->render(ZugferdRenderer::TYPE, $this->placeOrder(...$order)), $category);
    }

    public function testWithoutTheVatIdSettingTheInvoiceKeepsCategoryZ(): void
    {
        $orderId = $this->placeOrder();

        $this->changeDocumentConfig('invoice', ['vatId' => '']);

        $this->assertNoIntraCommunitySupply($this->render(ZugferdRenderer::TYPE, $orderId), 'Z');
    }

    public function testEachDocumentTypeReadsItsOwnVatIdSetting(): void
    {
        $orderId = $this->placeOrder();
        $invoiceId = $this->createInvoice($orderId);
        $this->addCreditItemToOrder($orderId);

        $this->changeDocumentConfig('credit_note', ['vatId' => '']);

        $this->assertCategory($this->render(ZugferdRenderer::TYPE, $orderId), 'K', '0.00');
        $this->assertNoIntraCommunitySupply($this->render(ZugferdCreditNoteRenderer::TYPE, $orderId, $invoiceId), 'Z');
    }

    public function testAVatIdInSmallLettersIsWrittenInCapitals(): void
    {
        $this->changeFrenchVatIdCheck(false);

        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeOrder(vatIds: ['fr12345678901']));

        $this->assertCategory($xml, 'K', '0.00');
        static::assertSame([self::VAT_ID], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    public function testTheVatIdAPluginChoseMakesTheOrderTaxFreeAndNamesTheBuyer(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            ResolveVatIdsExtension::onPre(),
            static function (ResolveVatIdsExtension $extension): void {
                $extension->result = [self::VAT_ID];
                $extension->stopPropagation();
            }
        );

        $orderId = $this->placeOrder(vatIds: [self::SELLER_VAT_ID, self::VAT_ID]);

        static::assertSame(CartPrice::TAX_STATE_FREE, $this->getOrderTaxStatus($orderId));
        $xml = $this->render(ZugferdRenderer::TYPE, $orderId);
        $this->assertCategory($xml, 'K', '0.00');
        static::assertSame([self::VAT_ID], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    public function testADispatchCountryAPluginChoseDecidesTheInvoice(): void
    {
        $orderId = $this->placeOrder();

        $franceId = $this->getCountryId('FR');
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            ResolveDispatchCountryExtension::onPre(),
            static function (ResolveDispatchCountryExtension $extension) use ($franceId): void {
                $extension->result = $franceId;
                $extension->stopPropagation();
            }
        );

        $this->assertNoIntraCommunitySupply($this->render(ZugferdRenderer::TYPE, $orderId), 'Z');
    }

    public function testTheInvoiceReadsTheVatIdAndTheAddressSavedOnTheOrder(): void
    {
        $orderId = $this->placeOrder();

        static::getContainer()->get('customer.repository')->update([[
            'id' => $this->customerId,
            'vatIds' => [self::SELLER_VAT_ID],
            'defaultShippingAddressId' => $this->addressIds['DE'],
        ]], $this->context);

        $this->assertIntraCommunitySupply($this->render(ZugferdRenderer::TYPE, $orderId), $orderId, '100.00');
    }

    public function testEachSalesChannelReadsItsOwnVatIdSetting(): void
    {
        $defaultOrderId = $this->placeOrder();
        $otherOrderId = $this->placeOrder();

        $otherSalesChannelId = $this->createSalesChannelWithoutVatId();
        static::getContainer()->get('order.repository')->update([
            ['id' => $otherOrderId, 'salesChannelId' => $otherSalesChannelId],
        ], $this->context);

        $this->assertNoIntraCommunitySupply($this->render(ZugferdRenderer::TYPE, $otherOrderId), 'Z');
        $this->assertCategory($this->render(ZugferdRenderer::TYPE, $defaultOrderId), 'K', '0.00');
    }

    #[DataProvider('documentTypes')]
    public function testTheXmlOfAnIntraCommunityDeliveryMatchesTheSnapshot(string $documentType): void
    {
        $orderId = $this->placeOrder();
        $invoiceId = $this->createInvoice($orderId);
        if ($documentType === ZugferdCreditNoteRenderer::TYPE) {
            $this->addCreditItemToOrder($orderId);
        }

        $this->assertSnapshot($documentType . '_intra_community_supply', [
            ['type' => self::TYPE_XML, 'actual' => $this->render($documentType, $orderId, $invoiceId)],
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function documentTypes(): iterable
    {
        yield 'invoice' => [ZugferdRenderer::TYPE];
        yield 'cancellation invoice' => [ZugferdCancellationInvoiceRenderer::TYPE];
        yield 'credit note' => [ZugferdCreditNoteRenderer::TYPE];
    }

    private function createSalesChannelWithoutVatId(): string
    {
        $salesChannelId = Uuid::randomHex();

        static::getContainer()->get('sales_channel.repository')->create([[
            'id' => $salesChannelId,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'name' => 'Second sales channel',
            'accessKey' => 'SWSC' . Uuid::randomHex(),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'currencyId' => Defaults::CURRENCY,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'shippingMethodId' => $this->shippingMethodId,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getCountryId('DE'),
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ]], $this->context);

        $this->changeDocumentConfig('invoice', ['vatId' => ''], $salesChannelId);

        return $salesChannelId;
    }
}
