<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ProductExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExportGenerator;
use Shopware\Core\Content\ProductExport\Service\ProductExportGeneratorInterface;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TranslationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
class AgenticAiProductExportFlowTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use TranslationTestBehaviour;

    /**
     * @var EntityRepository<ProductExportCollection>
     */
    private EntityRepository $productExportRepository;

    private ProductExportGeneratorInterface $productExportGenerator;

    private Context $context;

    protected function setUp(): void
    {
        $this->productExportRepository = static::getContainer()->get('product_export.repository');
        $this->productExportGenerator = static::getContainer()->get(ProductExportGenerator::class);
        $this->context = Context::createDefaultContext();
    }

    public function testAgenticAiSalesChannelProvisionsAndGeneratesOpenAiFeed(): void
    {
        $product = $this->createExportableProduct();
        $this->createProductStreamForProduct($product['id']);

        $agenticSalesChannel = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_AGENTIC_AI,
            'name' => 'Agentic AI Feed',
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://agentic-ai.localhost',
                ],
            ],
        ]);

        $productExport = $this->loadProvisionedProductExport($agenticSalesChannel['id']);

        static::assertSame(ProductExportEntity::FILE_FORMAT_JSONL, $productExport->getFileFormat());
        static::assertSame('openai-products-' . substr($agenticSalesChannel['id'], 0, 8) . '.jsonl', $productExport->getFileName());

        $result = $this->productExportGenerator->generate($productExport, new ExportBehavior());

        static::assertNotNull($result);
        static::assertFalse($result->hasErrors(), 'The generated feed must be valid JSONL without export errors.');

        $lines = array_values(array_filter(
            preg_split('/\R/', $result->getContent()) ?: [],
            static fn (string $line): bool => trim($line) !== ''
        ));

        static::assertCount(1, $lines);
        static::assertJson($lines[0]);

        /** @var array<string, mixed> $exportedProduct */
        $exportedProduct = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($product['productNumber'], $exportedProduct['item_id']);
        static::assertSame('OpenAI Feed Product', $exportedProduct['title']);
        static::assertSame('Feed description', $exportedProduct['description']);
        static::assertSame('10.99 EUR', $exportedProduct['price']);
        static::assertSame('in_stock', $exportedProduct['availability']);
        static::assertSame('ACME', $exportedProduct['brand']);
        static::assertSame('new', $exportedProduct['condition']);
        static::assertSame($product['id'], $exportedProduct['group_id']);
        static::assertFalse($exportedProduct['listing_has_variations']);
        static::assertSame('DE', $exportedProduct['store_country']);
        static::assertSame(['DE'], $exportedProduct['target_countries']);
        static::assertSame('1234567890123', $exportedProduct['gtin']);
        static::assertSame('MPN-123', $exportedProduct['mpn']);
        static::assertSame($productExport->getStorefrontSalesChannel()?->getName(), $exportedProduct['seller_name']);
        static::assertSame($productExport->getSalesChannelDomain()?->getUrl(), $exportedProduct['seller_url']);
        static::assertStringContainsString((string) $productExport->getSalesChannelDomain()?->getUrl(), $exportedProduct['url']);
        static::assertStringStartsWith('https://example.com/images/openai-feed-product.jpg', $exportedProduct['image_url']);
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

    private function createProductStreamForProduct(string $productId): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
                VALUES (
                    UNHEX('137B079935714281BA80B40F83F8D7EB'),
                    :apiFilter,
                    0,
                    '2019-08-16 08:43:57.488',
                    NULL
                )
            SQL,
            [
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
                    (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX('137B079935714281BA80B40F83F8D7EB'), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                    (UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 1, NULL, '2019-08-16 08:43:57.478', NULL),
                    (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, :productId, NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL)
            SQL,
            ['productId' => $productId]
        );
    }

    private function loadProvisionedProductExport(string $salesChannelId): ProductExportEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addAssociations([
            'salesChannel',
            'storefrontSalesChannel',
            'salesChannelDomain.language.locale',
        ]);

        $productExport = $this->productExportRepository->search($criteria, $this->context)->first();

        static::assertInstanceOf(ProductExportEntity::class, $productExport, 'Creating an Agentic AI sales channel must provision one product export.');

        return $productExport;
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
