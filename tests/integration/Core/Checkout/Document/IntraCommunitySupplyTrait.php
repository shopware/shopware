<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document;

use horstoeko\zugferd\ZugferdDocumentReader;
use horstoeko\zugferd\ZugferdXsdValidator;
use PHPUnit\Framework\Attributes\After;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\PromotionLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\IntraCommunityTaxResolver;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader as DocumentV2ConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * A German shop selling to a French business customer, the fixture of the e-invoice VAT category story.
 * Order A, the intra-community delivery, is {@see self::placeOrder()} with its defaults.
 * The customer also has an address in the Netherlands, a member state with *Tax-free (B2B)*, for the reverse-charge story.
 *
 * @internal
 */
#[Package('after-sales')]
trait IntraCommunitySupplyTrait
{
    use CountryAddToSalesChannelTestBehaviour;
    use DocumentV2Trait;

    private const VAT_ID = 'FR12345678901';

    /**
     * The VAT ID of the reverse-charge story, of a member state that is neither the seller's nor the billing country
     */
    private const FOREIGN_VAT_ID = 'NL123456789B01';

    private const SELLER_VAT_ID = 'DE123456789';

    private const SELLER_TAX_NUMBER = '12/345/67890';

    private const FR_WITHOUT_ZIP_CODE = 'FR without zip code';

    private const CORRECTION_NUMBER = '2000';

    private const RAM_NAMESPACE = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const UDT_NAMESPACE = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    private const BREAKDOWN = '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax';

    private const SUMMATION = '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/';

    private string $customerId;

    /**
     * @var array<string, string> ISO code => id of the customer's address in that country
     */
    private array $addressIds;

    private string $shippingMethodId;

    private IdsCollection $ids;

    /**
     * The orders of the story whose e-invoice states no intra-community supply, with the category their XML keeps.
     *
     * Order H of that story, tax-free without delivery, is a reverse-charge order since the reverse-charge story.
     *
     * @return iterable<string, array{0: array{billingCountry?: string, shippingCountry?: string, vatIds?: list<string>, accountType?: string, withPromotion?: bool}, 1: string, 2?: string}>
     */
    public static function ordersWithoutIntraCommunitySupply(): iterable
    {
        yield 'order B, taxed with the rate of the seller country' => [['vatIds' => [self::SELLER_VAT_ID], 'withPromotion' => false], 'S'];
        yield 'order C, tax-free delivery to a third country' => [['billingCountry' => 'CH', 'shippingCountry' => 'CH', 'withPromotion' => false], 'Z'];
        yield 'order D, tax-free private customer' => [['billingCountry' => 'CH', 'shippingCountry' => 'CH', 'vatIds' => [], 'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE, 'withPromotion' => false], 'Z'];
        // The default French pattern requires the country code since 6.6, the one shipped before did not
        yield 'order E, tax-free with a VAT ID without country code' => [['vatIds' => ['12345678901']], 'Z', '(FR)?[0-9A-Z]{2}[0-9]{9}'];
        yield 'order G, tax-free to a delivery address without zip code' => [['billingCountry' => 'DE', 'shippingCountry' => self::FR_WITHOUT_ZIP_CODE], 'Z'];
    }

    /**
     * The E-book orders of the reverse-charge story whose e-invoice states no reverse charge, with the category their XML keeps.
     *
     * @return iterable<string, array{0: array{billingCountry?: string, shippingCountry?: string, vatIds?: list<string>, accountType?: string}, 1: string, 2?: string, 3?: string}>
     */
    public static function ordersWithoutReverseCharge(): iterable
    {
        yield 'order B, taxed with the rate of the billing country' => [['shippingCountry' => 'CH', 'vatIds' => []], 'S', '20.00'];
        yield 'order C, taxed with the rate of the seller country' => [['shippingCountry' => 'CH', 'vatIds' => [self::SELLER_VAT_ID]], 'S'];
        yield 'order D, billed to a third country, taxed with the rate of the shipping country' => [['billingCountry' => 'CH', 'shippingCountry' => 'DE', 'vatIds' => [self::FOREIGN_VAT_ID]], 'S'];
        yield 'a tax-free private customer' => [['vatIds' => [], 'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE], 'Z'];
        // The default French pattern requires the country code since 6.6, the one shipped before did not
        yield 'order G, tax-free with a VAT ID without country code' => [['shippingCountry' => 'DE', 'vatIds' => ['12345678901']], 'Z', '19.00', '(FR)?[0-9A-Z]{2}[0-9]{9}'];
    }

    /**
     * The DocumentV2 pipeline switches the translator to the order's language.
     */
    #[After]
    public function resetTranslator(): void
    {
        static::getContainer()->get(Translator::class)->reset();
    }

    private function setUpIntraCommunitySupply(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        // Without a seller country the shop supplies from the sales channel's default country, Germany
        static::getContainer()->get(SystemConfigService::class)->set('core.basicInformation.sellerCountryId', null);

        $companyTax = ['enabled' => true, 'amount' => 0, 'currencyId' => Defaults::CURRENCY];
        static::getContainer()->get('country.repository')->update([
            ['id' => $this->getCountryId('FR'), 'active' => true, 'isEu' => true, 'checkVatIdPattern' => true, 'vatIdRequired' => false, 'companyTax' => $companyTax],
            ['id' => $this->getCountryId('NL'), 'active' => true, 'isEu' => true, 'checkVatIdPattern' => true, 'vatIdRequired' => false, 'companyTax' => $companyTax],
            ['id' => $this->getCountryId('CH'), 'active' => true, 'isEu' => false, 'companyTax' => $companyTax, 'customerTax' => $companyTax],
        ], $this->context);
        $this->addCountriesToSalesChannel([$this->getCountryId('FR'), $this->getCountryId('NL'), $this->getCountryId('CH')]);
        $this->resetIntraCommunityDecision();

        static::getContainer()->get('customer_group.repository')->update([
            ['id' => TestDefaults::FALLBACK_CUSTOMER_GROUP, 'displayGross' => false],
        ], $this->context);

        $this->createProducts();
        $this->shippingMethodId = $this->createShippingMethod(10.0);
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), 'TENOFF', 10.0, null, static::getContainer());
        $this->createBusinessCustomer();

        foreach (['invoice', 'storno', 'credit_note'] as $documentType) {
            $this->upsertBaseConfig([
                ...$this->getDemoInvoiceLegacyConfig(),
                'companyCountryId' => $this->getCountryId('DE'),
                'vatId' => self::SELLER_VAT_ID,
                'taxNumber' => self::SELLER_TAX_NUMBER,
                'paymentDueDate' => '+30 days',
                'displayAdditionalNoteDelivery' => false,
            ], $documentType);
        }
    }

    /**
     * @param list<string> $vatIds
     * @param list<string> $products
     */
    private function placeOrder(
        string $billingCountry = 'FR',
        string $shippingCountry = 'FR',
        array $vatIds = [self::VAT_ID],
        string $accountType = CustomerEntity::ACCOUNT_TYPE_BUSINESS,
        array $products = ['widget'],
        bool $withPromotion = true,
        string $languageId = Defaults::LANGUAGE_SYSTEM,
    ): string {
        static::getContainer()->get('customer.repository')->update([[
            'id' => $this->customerId,
            'accountType' => $accountType,
            'vatIds' => $vatIds,
            'defaultBillingAddressId' => $this->addressIds[$billingCountry],
            'defaultShippingAddressId' => $this->addressIds[$shippingCountry],
        ]], $this->context);

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->customerId,
                SalesChannelContextService::SHIPPING_METHOD_ID => $this->shippingMethodId,
                SalesChannelContextService::PAYMENT_METHOD_ID => $this->getInvoicePaymentMethodId(),
                SalesChannelContextService::LANGUAGE_ID => $languageId,
            ]
        );

        $cartService = static::getContainer()->get(CartService::class);
        $cart = $cartService->createNew($this->salesChannelContext->getToken());
        $lineItems = [];
        foreach ($products as $product) {
            $productId = $this->ids->get($product);
            $lineItems[] = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $productId, 'referencedId' => $productId], $this->salesChannelContext);
        }
        if ($withPromotion) {
            $lineItems[] = (new PromotionLineItemFactory())->create(['referencedId' => 'TENOFF'], $this->salesChannelContext);
        }

        $orderId = $this->persistCart($cartService->add($cart, $lineItems, $this->salesChannelContext));
        $this->enrichOrderForRendering($orderId);

        return $orderId;
    }

    /**
     * Foreign VAT ID, billed to France, shipped to Germany, only the E-book: tax-free, without delivery.
     */
    private function placeReverseChargeOrder(string $languageId = Defaults::LANGUAGE_SYSTEM): string
    {
        return $this->placeOrder(shippingCountry: 'DE', vatIds: [self::FOREIGN_VAT_ID], products: ['ebook'], withPromotion: false, languageId: $languageId);
    }

    /**
     * Foreign VAT ID, billed to France, shipped to the Netherlands, only the E-book: tax-free, and the promotion creates a delivery.
     */
    private function placeReverseChargeOrderWithDelivery(): string
    {
        return $this->placeOrder(shippingCountry: 'NL', vatIds: [self::FOREIGN_VAT_ID], products: ['ebook']);
    }

    /**
     * Foreign VAT ID, billed to Germany, shipped to France, the E-book and the Widget: a tax-free intra-community delivery.
     */
    private function placeMixedOrder(): string
    {
        return $this->placeOrder(billingCountry: 'DE', vatIds: [self::FOREIGN_VAT_ID], products: ['ebook', 'widget'], withPromotion: false);
    }

    /**
     * An E-book order of {@see self::ordersWithoutReverseCharge()}, with the country settings it needs.
     *
     * @param array{billingCountry?: string, shippingCountry?: string, vatIds?: list<string>, accountType?: string} $order
     */
    private function placeOrderWithoutReverseCharge(array $order, ?string $frenchVatIdPattern = null): string
    {
        if ($frenchVatIdPattern !== null) {
            $this->changeFrenchVatIdCheck(true, $frenchVatIdPattern);
        }
        if (($order['accountType'] ?? null) === CustomerEntity::ACCOUNT_TYPE_PRIVATE) {
            $this->exemptFrenchPrivateCustomers();
        }

        return $this->placeOrder(...[...$order, 'products' => ['ebook'], 'withPromotion' => false]);
    }

    /**
     * Renders a document of the legacy pipeline.
     *
     * @param array<string, mixed> $config
     */
    private function render(
        string $documentType,
        string $orderId,
        ?string $invoiceId = null,
        array $config = [],
        string $fileType = FileTypes::XML,
    ): string {
        $operation = new DocumentGenerateOperation($orderId, $fileType, $config, $invoiceId);

        $result = static::getContainer()->get(DocumentRendererRegistry::class)
            ->render($documentType, [$orderId => $operation], $this->context, new DocumentRendererConfig());

        static::assertSame([], array_map(static fn (\Throwable $error): string => $error->getMessage(), $result->getErrors()));
        $document = $result->getOrderSuccess($orderId);
        static::assertNotNull($document);

        return $document->getContent();
    }

    /**
     * The invoice a cancellation invoice or a credit note of the legacy pipeline refers to.
     */
    private function createInvoice(string $orderId): string
    {
        $invoice = $this->createDocument('invoice', $orderId, ['documentNumber' => self::DOCUMENT_NUMBER, 'documentDate' => self::DOCUMENT_DATE], $this->context)->first();
        static::assertNotNull($invoice);

        return $invoice->getId();
    }

    /**
     * Generates a document of the DocumentV2 pipeline.
     *
     * @return string the content of the stored file
     */
    private function generate(
        DocumentType $documentType,
        string $orderId,
        ?string $referencedDocumentId = null,
        DocumentFormat $format = DocumentFormat::ZUGFERD_XML,
    ): string {
        return $this->getFileContent($this->generateDocument($documentType, $orderId, $referencedDocumentId, $format), $format);
    }

    private function getFileContent(string $documentId, DocumentFormat $format): string
    {
        /** @var EntityRepository<DocumentFileCollection> $documentFileRepository */
        $documentFileRepository = static::getContainer()->get('document_file.repository');
        $file = $documentFileRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('documentId', $documentId))
                ->addFilter(new EqualsFilter('documentFormat', $format->value)),
            $this->context,
        )->getEntities()->first();
        static::assertNotNull($file);

        return static::getContainer()->get(MediaService::class)->loadFile($file->getMediaId(), $this->context);
    }

    /**
     * The invoice a cancellation invoice or a credit note of the DocumentV2 pipeline refers to.
     */
    private function generateInvoice(string $orderId): string
    {
        return $this->generateDocument(DocumentType::INVOICE, $orderId, null, DocumentFormat::ZUGFERD_XML);
    }

    private function generateDocument(DocumentType $documentType, string $orderId, ?string $referencedDocumentId, DocumentFormat $format): string
    {
        return static::getContainer()->get(DocumentGenerator::class)->generate(
            new DocumentGenerationRequest(
                $orderId,
                $documentType,
                [$format],
                $documentType === DocumentType::INVOICE ? self::DOCUMENT_NUMBER : self::CORRECTION_NUMBER,
                documentDate: self::DOCUMENT_DATE,
                referencedDocumentId: $referencedDocumentId,
            ),
            $this->context,
        )->getId();
    }

    private function getOrderTaxStatus(string $orderId): ?string
    {
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        return $orderRepository->search(new Criteria([$orderId]), $this->context)->getEntities()->first()?->getTaxStatus();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function changeDocumentConfig(string $documentType, array $config, ?string $salesChannelId = null): void
    {
        $this->upsertBaseConfig($config + (array) $this->getBaseConfig($documentType)?->getConfig(), $documentType, $salesChannelId);

        static::getContainer()->get(DocumentConfigLoader::class)->reset();
        static::getContainer()->get(DocumentV2ConfigLoader::class)->reset();
    }

    private function changeFrenchVatIdCheck(bool $checkVatIdPattern, ?string $vatIdPattern = null): void
    {
        static::getContainer()->get('country.repository')->update([
            array_filter([
                'id' => $this->getCountryId('FR'),
                'checkVatIdPattern' => $checkVatIdPattern,
                'vatIdPattern' => $vatIdPattern,
            ], static fn ($value) => $value !== null),
        ], $this->context);

        $this->resetIntraCommunityDecision();
    }

    /**
     * Turns on *Tax-free (B2C)* for France.
     */
    private function exemptFrenchPrivateCustomers(): void
    {
        static::getContainer()->get('country.repository')->update([[
            'id' => $this->getCountryId('FR'),
            'customerTax' => ['enabled' => true, 'amount' => 0, 'currencyId' => Defaults::CURRENCY],
        ]], $this->context);

        $this->resetIntraCommunityDecision();
    }

    private function resetIntraCommunityDecision(): void
    {
        static::getContainer()->get(VatIdPatternProvider::class)->reset();
        static::getContainer()->get(IntraCommunityTaxResolver::class)->reset();
    }

    private function getInvoicePaymentMethodId(): string
    {
        $paymentMethodId = static::getContainer()->get('payment_method.repository')->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'payment_invoicepayment')),
            $this->context
        )->firstId();
        static::assertIsString($paymentMethodId);

        return $paymentMethodId;
    }

    private function getCountryId(string $iso): string
    {
        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');

        $countryId = $countryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', $iso)), $this->context)->firstId();
        static::assertIsString($countryId);

        return $countryId;
    }

    private function getShippingDateLatest(string $orderId): string
    {
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        $order = $orderRepository->search((new Criteria([$orderId]))->addAssociation('deliveries'), $this->context)->getEntities()->first();
        $shippingDateLatest = $order?->getDeliveries()?->first()?->getShippingDateLatest();
        static::assertNotNull($shippingDateLatest);

        return $shippingDateLatest->format('Ymd');
    }

    /**
     * Criteria 1, 2, 3 and 4 of the story, for any e-invoice of order A.
     */
    private function assertIntraCommunitySupply(string $xml, string $orderId, string $taxableAmount): void
    {
        $this->assertCategory($xml, 'K', '0.00');
        $this->assertExemptBreakdown($xml, $taxableAmount);
        $this->assertVatIds($xml);
        $this->assertDeliverTo($xml, $orderId);
    }

    /**
     * Every line, allowance, charge and breakdown has the category at the rate.
     */
    private function assertCategory(string $xml, string $category, string $rate): void
    {
        $categories = self::values($xml, '//ram:CategoryCode');
        static::assertNotSame([], $categories);
        static::assertSame(array_fill(0, \count($categories), $category), $categories);

        $rates = self::values($xml, '//ram:RateApplicablePercent');
        static::assertSame(array_fill(0, \count($rates), $rate), $rates);
    }

    private function assertExemptBreakdown(string $xml, string $taxableAmount, string $category = 'K', string $reasonCode = 'VATEX-EU-IC', string $reason = 'Intra-community supply'): void
    {
        static::assertSame([$category], self::values($xml, self::BREAKDOWN . '/ram:CategoryCode'));
        static::assertSame(['0.00'], self::values($xml, self::BREAKDOWN . '/ram:RateApplicablePercent'));
        static::assertSame(['0.00'], self::values($xml, self::BREAKDOWN . '/ram:CalculatedAmount'));
        static::assertSame([$taxableAmount], self::values($xml, self::BREAKDOWN . '/ram:BasisAmount'));
        static::assertSame([$reasonCode], self::values($xml, self::BREAKDOWN . '/ram:ExemptionReasonCode'));
        static::assertSame([$reason], self::values($xml, self::BREAKDOWN . '/ram:ExemptionReason'));
    }

    private function assertVatIds(string $xml, string $buyerVatId = self::VAT_ID): void
    {
        static::assertSame([$buyerVatId], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]'));
        static::assertSame([self::SELLER_VAT_ID], self::values($xml, '//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]'));
        static::assertSame([self::SELLER_TAX_NUMBER], self::values($xml, '//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="FC"]'));
    }

    /**
     * Criteria 19–22 of the reverse-charge story, for any e-invoice of a reverse-charge order.
     */
    private function assertReverseCharge(string $xml, string $taxableAmount, string $buyerVatId = self::FOREIGN_VAT_ID): void
    {
        $this->assertCategory($xml, 'AE', '0.00');
        $this->assertExemptBreakdown($xml, $taxableAmount, 'AE', 'VATEX-EU-AE', 'Reverse charge');
        $this->assertVatIds($xml, $buyerVatId);
        static::assertSame([], self::values($xml, '//ram:ShipToTradeParty'));
    }

    private function assertDeliverTo(string $xml, string $orderId): void
    {
        $address = '//ram:ShipToTradeParty/ram:PostalTradeAddress/';
        static::assertSame(['1 Rue de Rivoli'], self::values($xml, $address . 'ram:LineOne'));
        static::assertSame(['75001'], self::values($xml, $address . 'ram:PostcodeCode'));
        static::assertSame(['Paris'], self::values($xml, $address . 'ram:CityName'));
        static::assertSame(['FR'], self::values($xml, $address . 'ram:CountryID'));
        static::assertSame([$this->getShippingDateLatest($orderId)], self::values($xml, '//ram:ActualDeliverySupplyChainEvent//udt:DateTimeString'));
    }

    /**
     * The XML stays as it is without this story: no exemption reason, no buyer VAT ID and no deliver-to address.
     */
    private function assertNoIntraCommunitySupply(string $xml, string $category, string $standardRate = '19.00'): void
    {
        $this->assertCategory($xml, $category, $category === 'S' ? $standardRate : '0.00');
        static::assertSame([], self::values($xml, '//ram:ExemptionReason'));
        static::assertSame([], self::values($xml, '//ram:ExemptionReasonCode'));
        static::assertSame([], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration'));
        static::assertSame([], self::values($xml, '//ram:ShipToTradeParty'));
    }

    private function assertTotals(string $xml, string $total): void
    {
        static::assertSame([$total], self::values($xml, self::SUMMATION . 'ram:LineTotalAmount'));
        static::assertSame([$total], self::values($xml, self::SUMMATION . 'ram:GrandTotalAmount'));
    }

    private function assertXsdValid(string $xml): void
    {
        $validator = (new ZugferdXsdValidator(ZugferdDocumentReader::readAndGuessFromContent($xml)))->validate();

        static::assertSame([], $validator->validationErrors());
    }

    /**
     * @return list<string>
     */
    private static function values(string $xml, string $query): array
    {
        static::assertNotSame('', $xml);
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('ram', self::RAM_NAMESPACE);
        $xpath->registerNamespace('udt', self::UDT_NAMESPACE);

        $nodes = $xpath->query($query);
        static::assertInstanceOf(\DOMNodeList::class, $nodes);

        $values = [];
        foreach ($nodes as $node) {
            $values[] = trim((string) $node->nodeValue);
        }

        return $values;
    }

    private function createProducts(): void
    {
        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($this->ids, 'widget'))
                ->name('Widget')
                ->price(119.0, 100.0)
                ->tax('standard', 19)
                ->type(ProductDefinition::TYPE_PHYSICAL)
                ->visibility()
                ->build(),
            (new ProductBuilder($this->ids, 'ebook'))
                ->name('E-book')
                ->price(119.0, 100.0)
                ->tax('standard', 19)
                ->type(ProductDefinition::TYPE_DIGITAL)
                ->visibility()
                ->build(),
        ], $this->context);

        $entireCountry = static::getContainer()->get('tax_rule_type.repository')->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', EntireCountryRuleTypeFilter::TECHNICAL_NAME)),
            $this->context
        )->firstId();

        static::getContainer()->get('tax_rule.repository')->create([
            ['taxId' => $this->ids->get('standard'), 'taxRuleTypeId' => $entireCountry, 'countryId' => $this->getCountryId('DE'), 'taxRate' => 19.0],
            ['taxId' => $this->ids->get('standard'), 'taxRuleTypeId' => $entireCountry, 'countryId' => $this->getCountryId('FR'), 'taxRate' => 20.0],
        ], $this->context);
    }

    private function createBusinessCustomer(): void
    {
        $this->customerId = Uuid::randomHex();
        $this->addressIds = [
            'DE' => Uuid::randomHex(),
            'FR' => Uuid::randomHex(),
            'NL' => Uuid::randomHex(),
            'CH' => Uuid::randomHex(),
            self::FR_WITHOUT_ZIP_CODE => Uuid::randomHex(),
        ];

        $addresses = [];
        foreach ([
            'DE' => ['DE', 'Unter den Linden 1', '10115', 'Berlin'],
            'FR' => ['FR', '1 Rue de Rivoli', '75001', 'Paris'],
            'NL' => ['NL', 'Dam 1', '1012 AB', 'Amsterdam'],
            'CH' => ['CH', 'Bahnhofstrasse 1', '8001', 'Zürich'],
            self::FR_WITHOUT_ZIP_CODE => ['FR', '1 Rue de Rivoli', '', 'Paris'],
        ] as $key => [$iso, $street, $zipcode, $city]) {
            $addresses[] = [
                'id' => $this->addressIds[$key],
                'countryId' => $this->getCountryId($iso),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => $street,
                'zipcode' => $zipcode,
                'city' => $city,
            ];
        }

        static::getContainer()->get('customer.repository')->create([[
            'id' => $this->customerId,
            'customerNumber' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'company' => 'Exemple SARL',
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'guest' => true,
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => [self::VAT_ID],
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $this->addressIds['FR'],
            'defaultShippingAddressId' => $this->addressIds['FR'],
            'addresses' => $addresses,
        ]], $this->context);
    }
}
