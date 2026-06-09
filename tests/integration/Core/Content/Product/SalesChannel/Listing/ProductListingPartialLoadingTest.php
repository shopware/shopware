<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductListingPartialLoadingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const CONFIG_KEY = 'core.listing.partialDataLoading';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(SystemConfigService::class)->delete(self::CONFIG_KEY);
    }

    public function testListingLoadsPartialDataWhenEnabled(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, true);

        $result = $this->loadListing();

        $product = $result->getEntities()->get($this->ids->get('product0'));
        static::assertInstanceOf(PartialEntity::class, $product);

        $translated = $product->get('translated');
        static::assertIsArray($translated);
        static::assertArrayNotHasKey('description', array_filter($translated), 'description must not be loaded');
        static::assertNotEmpty($translated['descriptionTeaser'] ?? null, 'descriptionTeaser must be loaded');
        static::assertSame(512, mb_strlen($translated['descriptionTeaser']));
        static::assertStringNotContainsString('<', $translated['descriptionTeaser'], 'descriptionTeaser must not contain HTML');
        static::assertStringStartsWith('Lorem ipsum', $translated['descriptionTeaser']);

        static::assertInstanceOf(CalculatedPrice::class, $product->get('calculatedPrice'));

        $calculatedPrices = $product->get('calculatedPrices');
        static::assertNotNull($calculatedPrices);
        static::assertCount(1, $calculatedPrices, 'advanced rule prices must survive partial loading');

        $manufacturer = $product->get('manufacturer');
        static::assertNotNull($manufacturer);
        static::assertSame('probe-manufacturer', $manufacturer->get('translated')['name'] ?? null);
    }

    public function testListingLoadsFullDataByDefault(): void
    {
        $this->createData();

        // No config set: reduced loading is opt-in, so full entities are loaded by default.
        $result = $this->loadListing();

        $product = $result->getEntities()->get($this->ids->get('product0'));
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertNotEmpty($product->getTranslation('description'));
        static::assertNotEmpty($product->getTranslation('descriptionTeaser'));
    }

    public function testListingLoadsFullDataWhenDisabled(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, false);

        $result = $this->loadListing();

        $product = $result->getEntities()->get($this->ids->get('product0'));
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertNotEmpty($product->getTranslation('description'));
        static::assertNotEmpty($product->getTranslation('descriptionTeaser'));
    }

    public function testExplicitCriteriaFieldsAreNotOverridden(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, true);

        $criteria = new Criteria();
        $criteria->addFields(['id', 'name', 'description']);

        $result = $this->loadListing($criteria);

        $product = $result->getEntities()->get($this->ids->get('product0'));
        static::assertInstanceOf(PartialEntity::class, $product);

        $translated = $product->get('translated');
        static::assertIsArray($translated);
        static::assertNotEmpty($translated['description'] ?? null, 'explicitly requested description must stay loaded');
        static::assertArrayNotHasKey('descriptionTeaser', array_filter($translated), 'partial field set must not be merged into explicit fields');
    }

    public function testPartialLoadingRendersCoverMedia(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, true);

        static::getContainer()->get('media.repository')->create([[
            'id' => $this->ids->create('media'),
            'fileName' => 'probe-image',
            'fileExtension' => 'png',
            'mimeType' => 'image/png',
            'path' => 'media/probe-image.png',
            'private' => false,
            'alt' => 'Probe alt text',
            'title' => 'Probe title',
        ]], Context::createDefaultContext());

        static::getContainer()->get('product.repository')->update([[
            'id' => $this->ids->get('product0'),
            'cover' => ['id' => $this->ids->create('product-media'), 'mediaId' => $this->ids->get('media')],
        ]], Context::createDefaultContext());

        $product = $this->loadListing()->getEntities()->get($this->ids->get('product0'));
        static::assertInstanceOf(PartialEntity::class, $product);

        $media = $product->get('cover')?->get('media');
        static::assertInstanceOf(PartialEntity::class, $media);

        // The storefront product box gates the cover image on `cover.url`; it must be resolved despite partial loading.
        static::assertNotEmpty($media->get('url'));
        static::assertSame('Probe alt text', $media->get('translated')['alt'] ?? null);
        // MediaLoadedSubscriber must restore thumbnails for partially loaded media as well.
        static::assertInstanceOf(MediaThumbnailCollection::class, $media->get('thumbnails'));

        // `sw_encode_media_url` must accept the partial media without a type error and yield its url.
        $encoded = static::getContainer()->get(UrlEncodingTwigFilter::class)->encodeMediaUrl($media);
        static::assertIsString($encoded);
        static::assertStringContainsString('probe-image.png', $encoded);
    }

    private function loadListing(?Criteria $criteria = null): ProductListingResult
    {
        $context = $this->buildSalesChannelContext();

        return static::getContainer()->get(ProductListingRoute::class)
            ->load($this->ids->get('category'), new Request(), $context, $criteria ?? new Criteria())
            ->getResult();
    }

    private function buildSalesChannelContext(): SalesChannelContext
    {
        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->ids->get('sales-channel'));
        $context->setRuleIds([$this->ids->get('rule')]);

        return $context;
    }

    private function createData(): void
    {
        static::getContainer()->get('rule.repository')->create([[
            'id' => $this->ids->create('rule'),
            'name' => 'probe-rule',
            'priority' => 1,
            'conditions' => [['type' => 'alwaysValid']],
        ]], Context::createDefaultContext());

        $products = [];
        for ($i = 0; $i < 3; ++$i) {
            $products[] = [
                'id' => $this->ids->create('product' . $i),
                'productNumber' => $this->ids->get('product' . $i),
                'name' => 'Probe product ' . $i,
                'description' => '<p style="color: red;">' . str_repeat('Lorem ipsum dolor sit amet. ', 500) . '</p>',
                'manufacturer' => ['id' => $this->ids->create('manufacturer'), 'name' => 'probe-manufacturer'],
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $this->ids->get('rule'),
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 90, 'linked' => false]],
                    ],
                ],
                'tax' => ['name' => 'probe-tax', 'taxRate' => 15],
                'active' => true,
            ];
        }

        static::getContainer()->get('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => 'Probe',
            'productAssignmentType' => 'product',
            'products' => $products,
        ]], Context::createDefaultContext());

        $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $visibilities = array_map(fn (array $product) => [
            'id' => $product['id'],
            'visibilities' => [
                ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ], $products);

        static::getContainer()->get('product.repository')->update($visibilities, Context::createDefaultContext());
    }
}
