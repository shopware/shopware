<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ProductExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExportGenerator;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingListener;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TranslationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('discovery')]
class AgenticCommerceProductExportFlowTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use TranslationTestBehaviour;

    /**
     * @var EntityRepository<ProductExportCollection>
     */
    private EntityRepository $productExportRepository;

    private ProductExportGenerator $productExportGenerator;

    private Context $context;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->productExportRepository = static::getContainer()->get('product_export.repository');
        $this->productExportGenerator = static::getContainer()->get(ProductExportGenerator::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testAgenticCommerceSalesChannelGeneratesOpenAiFeedFromExplicitProductExport(): void
    {
        $product = $this->createExportableProduct();
        $productStreamId = $this->createProductStreamForProduct($product['id']);

        $agenticSalesChannel = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE,
            'name' => 'Agentic Commerce Feed',
            'countries' => [
                ['id' => $this->getDefaultCountryId()],
            ],
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://agentic-commerce.localhost',
                ],
            ],
        ]);

        $productExport = $this->createProductExport($agenticSalesChannel['id'], $productStreamId);

        static::assertSame(ProductExportEntity::FILE_FORMAT_JSONL, $productExport->getFileFormat());
        static::assertSame('openai-products-' . substr($agenticSalesChannel['id'], 0, 8) . '.jsonl', $productExport->getFileName());

        $result = $this->productExportGenerator->generate($productExport, new ExportBehavior());

        static::assertNotNull($result);
        static::assertFalse($result->hasErrors());

        $lines = array_values(array_filter(
            preg_split('/\R/', $result->getContent()) ?: [],
            static fn (string $line): bool => trim($line) !== ''
        ));

        static::assertCount(1, $lines);
        static::assertJson($lines[0]);

        $exportedProduct = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($exportedProduct);

        static::assertSame($product['productNumber'], $exportedProduct['item_id']);
        static::assertTrue($exportedProduct['is_eligible_search']);
        static::assertFalse($exportedProduct['is_eligible_checkout']);
        static::assertSame('OpenAI Feed Product', $exportedProduct['title']);
        static::assertSame('Feed description', $exportedProduct['description']);
        static::assertSame('10.99 EUR', $exportedProduct['price']);
        static::assertSame('in_stock', $exportedProduct['availability']);
        static::assertSame('ACME', $exportedProduct['brand']);
        static::assertArrayNotHasKey('condition', $exportedProduct);
        static::assertFalse($exportedProduct['listing_has_variations']);
        static::assertArrayNotHasKey('group_id', $exportedProduct);
        static::assertArrayNotHasKey('item_group_title', $exportedProduct);
        static::assertArrayNotHasKey('offer_id', $exportedProduct);
        static::assertArrayNotHasKey('variant_dict', $exportedProduct);
        static::assertFalse($exportedProduct['is_digital']);
        static::assertSame('DE', $exportedProduct['store_country']);
        static::assertIsArray($exportedProduct['target_countries']);
        static::assertNotEmpty($exportedProduct['target_countries']);
        static::assertContains('DE', $exportedProduct['target_countries']);
        static::assertSame('1234567890123', $exportedProduct['gtin']);
        static::assertSame('MPN-123', $exportedProduct['mpn']);
        static::assertSame($productExport->getStorefrontSalesChannel()?->getName(), $exportedProduct['seller_name']);
        static::assertSame($productExport->getSalesChannelDomain()?->getUrl(), $exportedProduct['seller_url']);
        static::assertSame($productExport->getSalesChannelDomain()?->getUrl(), $exportedProduct['return_policy']);
        static::assertStringContainsString((string) $productExport->getSalesChannelDomain()?->getUrl(), $exportedProduct['url']);

        $query = parse_url($exportedProduct['url'], \PHP_URL_QUERY);
        parse_str(\is_string($query) ? $query : '', $queryParameters);
        static::assertSame($agenticSalesChannel['id'], $queryParameters[SalesChannelTrackingListener::QUERY_PARAM] ?? null);

        static::assertStringStartsWith('https://example.com/images/openai-feed-product.jpg', $exportedProduct['image_url']);
    }

    /**
     * @return array<string, array{config: array<string, string>, expectedAffiliate: ?string, expectedCampaign: ?string}>
     */
    public static function provideTrackingCodeCombinations(): array
    {
        return [
            'no tracking codes' => [
                'config' => [],
                'expectedAffiliate' => null,
                'expectedCampaign' => null,
            ],
            'affiliate code only' => [
                'config' => [OrderService::AFFILIATE_CODE_KEY => 'my-affiliate'],
                'expectedAffiliate' => 'my-affiliate',
                'expectedCampaign' => null,
            ],
            'campaign code only' => [
                'config' => [OrderService::CAMPAIGN_CODE_KEY => 'my-campaign'],
                'expectedAffiliate' => null,
                'expectedCampaign' => 'my-campaign',
            ],
            'both affiliate and campaign codes' => [
                'config' => [
                    OrderService::AFFILIATE_CODE_KEY => 'my-affiliate',
                    OrderService::CAMPAIGN_CODE_KEY => 'my-campaign',
                ],
                'expectedAffiliate' => 'my-affiliate',
                'expectedCampaign' => 'my-campaign',
            ],
        ];
    }

    /**
     * @param array<string, string> $config
     */
    #[DataProvider('provideTrackingCodeCombinations')]
    public function testProductUrlContainsConfiguredTrackingCodes(
        array $config,
        ?string $expectedAffiliate,
        ?string $expectedCampaign,
    ): void {
        $product = $this->createExportableProduct();
        $productStreamId = $this->createProductStreamForProduct($product['id']);

        $agenticSalesChannel = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE,
            'name' => 'Agentic Commerce Feed',
            'configuration' => $config,
            'countries' => [
                ['id' => $this->getDefaultCountryId()],
            ],
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://agentic-commerce.localhost',
                ],
            ],
        ]);

        $productExport = $this->createProductExport($agenticSalesChannel['id'], $productStreamId);
        $result = $this->productExportGenerator->generate($productExport, new ExportBehavior());

        static::assertNotNull($result);
        static::assertFalse($result->hasErrors());

        $lines = array_values(array_filter(
            preg_split('/\R/', $result->getContent()) ?: [],
            static fn (string $line): bool => trim($line) !== '',
        ));

        static::assertCount(1, $lines);

        $exportedProduct = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($exportedProduct);
        static::assertArrayHasKey('url', $exportedProduct);

        $query = parse_url($exportedProduct['url'], \PHP_URL_QUERY);
        parse_str(\is_string($query) ? $query : '', $queryParameters);

        static::assertSame(
            $agenticSalesChannel['id'],
            $queryParameters[SalesChannelTrackingListener::QUERY_PARAM] ?? null,
            'referringSalesChannel must always be present in the product URL',
        );

        if ($expectedAffiliate !== null) {
            static::assertSame($expectedAffiliate, $queryParameters[OrderService::AFFILIATE_CODE_KEY] ?? null);
        } else {
            static::assertArrayNotHasKey(OrderService::AFFILIATE_CODE_KEY, $queryParameters);
        }

        if ($expectedCampaign !== null) {
            static::assertSame($expectedCampaign, $queryParameters[OrderService::CAMPAIGN_CODE_KEY] ?? null);
        } else {
            static::assertArrayNotHasKey(OrderService::CAMPAIGN_CODE_KEY, $queryParameters);
        }
    }

    public function testAgenticCommerceSalesChannelGeneratesMappedVariantFieldsForOpenAiFeed(): void
    {
        $variant = $this->createExportableVariantProduct();
        $productStreamId = $this->createProductStreamForProduct($variant['id']);

        $agenticSalesChannel = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE,
            'name' => 'Agentic Commerce Feed',
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://agentic-commerce.localhost',
                ],
            ],
        ]);

        $salesChannelId = $agenticSalesChannel['id'];
        $this->systemConfigService->set('core.openAiProductExport.variantColor', [$variant['colorGroupId']], $salesChannelId);
        $this->systemConfigService->set('core.openAiProductExport.variantSize', [$variant['sizeGroupId']], $salesChannelId);
        $this->systemConfigService->set('core.openAiProductExport.variantGender', [$variant['genderGroupId']], $salesChannelId);
        $this->systemConfigService->set(
            'core.openAiProductExport.variantCustom',
            [
                $variant['colorGroupId'], // overlap with specific mapping -> must be ignored
                $variant['custom1GroupId'],
                $variant['custom2GroupId'],
                $variant['custom3GroupId'],
                $variant['custom4GroupId'], // 4th custom value -> must be ignored (max 3)
            ],
            $salesChannelId
        );

        $productExport = $this->createProductExport($salesChannelId, $productStreamId, true);
        $result = $this->productExportGenerator->generate($productExport, new ExportBehavior());

        static::assertNotNull($result);
        static::assertFalse($result->hasErrors(), 'The generated feed must be valid JSONL without export errors.');

        $lines = array_values(array_filter(
            preg_split('/\R/', $result->getContent()) ?: [],
            static fn (string $line): bool => trim($line) !== ''
        ));

        static::assertCount(1, $lines);
        static::assertJson($lines[0]);

        $exportedProduct = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($exportedProduct);

        static::assertTrue($exportedProduct['listing_has_variations']);
        static::assertSame('SKU-' . $variant['productNumber'] . '-10.99', $exportedProduct['offer_id']);
        static::assertSame($variant['parentId'], $exportedProduct['group_id']);
        static::assertSame('OpenAI Feed Variant', $exportedProduct['item_group_title']);

        static::assertSame('copper', $exportedProduct['color']);
        static::assertSame('XL', $exportedProduct['size']);
        static::assertSame('unisex', $exportedProduct['gender']);

        static::assertSame('plan', $exportedProduct['custom_variant1_category']);
        static::assertSame('12m', $exportedProduct['custom_variant1_option']);
        static::assertSame('material_custom', $exportedProduct['custom_variant2_category']);
        static::assertSame('silk', $exportedProduct['custom_variant2_option']);
        static::assertSame('finish', $exportedProduct['custom_variant3_category']);
        static::assertSame('matte', $exportedProduct['custom_variant3_option']);

        static::assertArrayNotHasKey('custom_variant4_category', $exportedProduct);
        static::assertArrayNotHasKey('custom_variant4_option', $exportedProduct);

        static::assertIsArray($exportedProduct['variant_dict']);
        static::assertSame('copper', $exportedProduct['variant_dict']['color']);
        static::assertSame('XL', $exportedProduct['variant_dict']['size']);
        static::assertSame('unisex', $exportedProduct['variant_dict']['gender']);
        static::assertSame('12m', $exportedProduct['variant_dict']['plan']);
        static::assertSame('silk', $exportedProduct['variant_dict']['material_custom']);
        static::assertSame('matte', $exportedProduct['variant_dict']['finish']);
        static::assertArrayNotHasKey('color_custom', $exportedProduct['variant_dict']);
    }

    public function testAgenticCommerceSalesChannelGeneratesGoogleFeedFromExplicitProductExport(): void
    {
        $product = $this->createExportableProduct();
        $productStreamId = $this->createProductStreamForProduct($product['id']);

        $agenticSalesChannel = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE,
            'name' => 'Agentic Commerce Feed',
            'countries' => [
                ['id' => $this->getDefaultCountryId()],
            ],
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://agentic-commerce.localhost',
                ],
            ],
        ]);

        $salesChannelId = $agenticSalesChannel['id'];

        $productExport = $this->createGoogleProductExport($salesChannelId, $productStreamId);

        static::assertSame(ProductExportEntity::FILE_FORMAT_XML, $productExport->getFileFormat());
        static::assertSame('google', $productExport->getProvider());

        $result = $this->productExportGenerator->generate($productExport, new ExportBehavior());

        static::assertNotNull($result);
        static::assertFalse($result->hasErrors());

        $content = $result->getContent();

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        static::assertNotFalse($xml, 'Generated Google feed must be valid XML.');

        $items = $xml->xpath('//item');
        static::assertIsArray($items);
        static::assertCount(1, $items);

        $item = $items[0];
        $googleChildren = $item->children('http://base.google.com/ns/1.0');

        static::assertSame($product['productNumber'], (string) $googleChildren->id);
        static::assertSame('OpenAI Feed Product', (string) $item->title);
        static::assertSame('Feed description', (string) $item->description);
        static::assertSame('in_stock', (string) $googleChildren->availability);
        static::assertSame('new', (string) $googleChildren->condition);
        static::assertSame('10.99 EUR', (string) $googleChildren->price);
        static::assertSame('ACME', (string) $googleChildren->brand);
        static::assertSame('1234567890123', (string) $googleChildren->gtin);
        static::assertSame('MPN-123', (string) $googleChildren->mpn);

        $link = (string) $item->link;
        static::assertStringStartsWith('http://agentic-commerce.localhost', $link);
        $query = parse_url($link, \PHP_URL_QUERY);
        parse_str(\is_string($query) ? $query : '', $queryParameters);
        static::assertSame($salesChannelId, $queryParameters[SalesChannelTrackingListener::QUERY_PARAM] ?? null);
    }

    /**
     * @return array{id: string, productNumber: string}
     */
    private function createExportableProduct(): array
    {
        $productRepository = static::getContainer()->get('product.repository');
        $storefrontSalesChannelId = $this->getDefaultStorefrontSalesChannelId();

        $productId = Uuid::randomHex();
        $productNumber = 'openai-feed-product';
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $productMediaId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'active' => true,
                'stock' => 5,
                'name' => 'OpenAI Feed Product',
                'description' => 'Feed description',
                'ean' => '1234567890123',
                'manufacturerNumber' => 'MPN-123',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 9.24, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'ACME'],
                'tax' => ['id' => $taxId, 'taxRate' => 19, 'name' => 'Standard'],
                'coverId' => $productMediaId,
                'media' => [
                    [
                        'id' => $productMediaId,
                        'position' => 1,
                        'media' => [
                            'id' => $mediaId,
                            'fileName' => 'openai-feed-product',
                            'fileExtension' => 'jpg',
                            'mimeType' => 'image/jpeg',
                            'path' => 'https://example.com/images/openai-feed-product.jpg',
                        ],
                    ],
                ],
                'visibilities' => [
                    ['salesChannelId' => $storefrontSalesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        return [
            'id' => $productId,
            'productNumber' => $productNumber,
        ];
    }

    private function createProductStreamForProduct(string $productId): string
    {
        $connection = static::getContainer()->get(Connection::class);
        $productStreamId = '137B079935714281BA80B40F83F8D7EB';

        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
                VALUES (
                    UNHEX(:productStreamId),
                    :apiFilter,
                    0,
                    '2019-08-16 08:43:57.488',
                    NULL
                )
            SQL,
            [
                'productStreamId' => $productStreamId,
                'apiFilter' => \sprintf(
                    '[{"type":"multi","queries":[{"type":"multi","queries":[{"type":"equalsAny","field":"product.id","value":"%s"}],"operator":"AND"}],"operator":"OR"}]',
                    $productId
                ),
            ]
        );

        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO `product_stream_filter`
                    (`id`, `product_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
                VALUES
                    (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX(:productStreamId), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                    (UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), UNHEX(:productStreamId), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 1, NULL, '2019-08-16 08:43:57.478', NULL),
                    (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX(:productStreamId), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, :productId, NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL)
            SQL,
            [
                'productId' => $productId,
                'productStreamId' => $productStreamId,
            ]
        );

        return strtolower($productStreamId);
    }

    private function createProductExport(string $salesChannelId, string $productStreamId, bool $includeVariants = false): ProductExportEntity
    {
        $salesChannel = $this->loadSalesChannel($salesChannelId);
        $domain = $salesChannel->getDomains()?->first();

        static::assertNotNull($domain);

        $productExportId = Uuid::randomHex();

        $this->productExportRepository->create([
            [
                'id' => $productExportId,
                'productStreamId' => $productStreamId,
                'storefrontSalesChannelId' => $this->getDefaultStorefrontSalesChannelId(),
                'salesChannelId' => $salesChannelId,
                'salesChannelDomainId' => $domain->getId(),
                'currencyId' => Defaults::CURRENCY,
                'fileName' => 'openai-products-' . substr($salesChannelId, 0, 8) . '.jsonl',
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_JSONL,
                'provider' => 'open-ai',
                'includeVariants' => $includeVariants,
                'generateByCronjob' => false,
                'interval' => 86400,
                'headerTemplate' => '',
                'bodyTemplate' => $this->getOpenAiBodyTemplate(),
                'footerTemplate' => '',
            ],
        ], $this->context);

        $criteria = new Criteria();
        $criteria->setIds([$productExportId]);
        $criteria->addAssociations([
            'salesChannel',
            'storefrontSalesChannel',
            'salesChannelDomain.language.locale',
        ]);

        $productExport = $this->productExportRepository->search($criteria, $this->context)->first();

        static::assertInstanceOf(ProductExportEntity::class, $productExport);

        return $productExport;
    }

    private function getDefaultCountryId(): string
    {
        $countryRepository = static::getContainer()->get('country.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('iso', 'DE'))
            ->setLimit(1);

        return $countryRepository->searchIds($criteria, $this->context)->firstId() ?? throw new \RuntimeException('Default country not found');
    }

    /**
     * @return array{
     *     id: string,
     *     parentId: string,
     *     productNumber: string,
     *     colorGroupId: string,
     *     sizeGroupId: string,
     *     genderGroupId: string,
     *     custom1GroupId: string,
     *     custom2GroupId: string,
     *     custom3GroupId: string,
     *     custom4GroupId: string
     * }
     */
    private function createExportableVariantProduct(): array
    {
        $productRepository = static::getContainer()->get('product.repository');
        $storefrontSalesChannelId = $this->getDefaultStorefrontSalesChannelId();

        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $productMediaId = Uuid::randomHex();

        $colorGroupId = Uuid::randomHex();
        $sizeGroupId = Uuid::randomHex();
        $genderGroupId = Uuid::randomHex();
        $custom1GroupId = Uuid::randomHex();
        $custom2GroupId = Uuid::randomHex();
        $custom3GroupId = Uuid::randomHex();
        $custom4GroupId = Uuid::randomHex();

        $productRepository->create([
            [
                'id' => $parentId,
                'productNumber' => 'openai-feed-parent',
                'active' => true,
                'stock' => 5,
                'name' => 'OpenAI Feed Parent',
                'description' => 'Feed parent description',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 9.24, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'ACME'],
                'tax' => ['id' => $taxId, 'taxRate' => 19, 'name' => 'Standard'],
                'visibilities' => [
                    ['salesChannelId' => $storefrontSalesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => $variantId,
                'parentId' => $parentId,
                'productNumber' => 'openai-feed-variant',
                'active' => true,
                'stock' => 5,
                'name' => 'OpenAI Feed Variant',
                'description' => 'Feed variant description',
                'ean' => '1234567890123',
                'manufacturerNumber' => 'MPN-123',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 9.24, 'linked' => false]],
                'manufacturerId' => $manufacturerId,
                'taxId' => $taxId,
                'coverId' => $productMediaId,
                'media' => [
                    [
                        'id' => $productMediaId,
                        'position' => 1,
                        'media' => [
                            'id' => $mediaId,
                            'fileName' => 'openai-feed-variant',
                            'fileExtension' => 'jpg',
                            'mimeType' => 'image/jpeg',
                            'path' => 'https://example.com/images/openai-feed-product.jpg',
                        ],
                    ],
                ],
                'visibilities' => [
                    ['salesChannelId' => $storefrontSalesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
                'options' => [
                    $this->createOptionPayload($colorGroupId, 'color', 'copper'),
                    $this->createOptionPayload($sizeGroupId, 'size', 'XL'),
                    $this->createOptionPayload($genderGroupId, 'gender', 'unisex'),
                    $this->createOptionPayload($custom1GroupId, 'plan', '12m'),
                    $this->createOptionPayload($custom2GroupId, 'material_custom', 'silk'),
                    $this->createOptionPayload($custom3GroupId, 'finish', 'matte'),
                    $this->createOptionPayload($custom4GroupId, 'color_custom', 'rose'),
                ],
            ],
        ], $this->context);

        return [
            'id' => $variantId,
            'parentId' => $parentId,
            'productNumber' => 'openai-feed-variant',
            'colorGroupId' => $colorGroupId,
            'sizeGroupId' => $sizeGroupId,
            'genderGroupId' => $genderGroupId,
            'custom1GroupId' => $custom1GroupId,
            'custom2GroupId' => $custom2GroupId,
            'custom3GroupId' => $custom3GroupId,
            'custom4GroupId' => $custom4GroupId,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     position: int,
     *     groupId: string,
     *     group: array{id: string, position: int, name: string},
     *     name: string
     * }
     */
    private function createOptionPayload(string $groupId, string $groupName, string $optionName): array
    {
        return [
            'id' => Uuid::randomHex(),
            'position' => 1,
            'groupId' => $groupId,
            'group' => [
                'id' => $groupId,
                'position' => 1,
                'name' => $groupName,
            ],
            'name' => $optionName,
        ];
    }

    private function getOpenAiBodyTemplate(): string
    {
        $template = file_get_contents(__DIR__ . '/../../../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/agentic-product-export-templates/open-ai/body.json.twig');

        static::assertIsString($template);

        return $template;
    }

    private function getGoogleHeaderTemplate(): string
    {
        $template = file_get_contents(__DIR__ . '/../../../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/agentic-product-export-templates/google/header.xml.twig');

        static::assertIsString($template);

        return $template;
    }

    private function getGoogleBodyTemplate(): string
    {
        $template = file_get_contents(__DIR__ . '/../../../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/agentic-product-export-templates/google/body.xml.twig');

        static::assertIsString($template);

        return $template;
    }

    private function getGoogleFooterTemplate(): string
    {
        $template = file_get_contents(__DIR__ . '/../../../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/agentic-product-export-templates/google/footer.xml.twig');

        static::assertIsString($template);

        return $template;
    }

    private function createGoogleProductExport(string $salesChannelId, string $productStreamId, bool $includeVariants = false): ProductExportEntity
    {
        $salesChannel = $this->loadSalesChannel($salesChannelId);
        $domain = $salesChannel->getDomains()?->first();

        static::assertNotNull($domain);

        $productExportId = Uuid::randomHex();

        $this->productExportRepository->create([
            [
                'id' => $productExportId,
                'productStreamId' => $productStreamId,
                'storefrontSalesChannelId' => $this->getDefaultStorefrontSalesChannelId(),
                'salesChannelId' => $salesChannelId,
                'salesChannelDomainId' => $domain->getId(),
                'currencyId' => Defaults::CURRENCY,
                'fileName' => 'google-products-' . substr($salesChannelId, 0, 8) . '.xml',
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_XML,
                'provider' => 'google',
                'includeVariants' => $includeVariants,
                'generateByCronjob' => false,
                'interval' => 86400,
                'headerTemplate' => $this->getGoogleHeaderTemplate(),
                'bodyTemplate' => $this->getGoogleBodyTemplate(),
                'footerTemplate' => $this->getGoogleFooterTemplate(),
            ],
        ], $this->context);

        $criteria = new Criteria();
        $criteria->setIds([$productExportId]);
        $criteria->addAssociations([
            'salesChannel',
            'storefrontSalesChannel',
            'salesChannelDomain.language.locale',
        ]);

        $productExport = $this->productExportRepository->search($criteria, $this->context)->first();

        static::assertInstanceOf(ProductExportEntity::class, $productExport);

        return $productExport;
    }

    private function loadSalesChannel(string $salesChannelId): SalesChannelEntity
    {
        /** @var EntityRepository<SalesChannelCollection> $repository */
        $repository = static::getContainer()->get('sales_channel.repository');

        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('domains');

        $salesChannel = $repository->search($criteria, $this->context)->first();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        return $salesChannel;
    }

    private function getDefaultStorefrontSalesChannelId(): string
    {
        /** @var EntityRepository<SalesChannelCollection> $repository */
        $repository = static::getContainer()->get('sales_channel.repository');

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addFilter(new EqualsFilter('active', true));

        $salesChannel = $repository->search($criteria, $this->context)->first();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        return $salesChannel->getId();
    }
}
