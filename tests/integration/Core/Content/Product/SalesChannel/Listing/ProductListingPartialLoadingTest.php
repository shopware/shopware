<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
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
#[Package('inventory')]
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

    public function testListingExcludesHeavyFieldsButKeepsFullEntity(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, true);

        $product = $this->loadListing()->getEntities()->get($this->ids->get('product0'));

        // Reduced loading drops only the heavy columns and keeps a full, typed entity (no PartialEntity).
        static::assertInstanceOf(SalesChannelProductEntity::class, $product);

        $translated = $product->getTranslated();
        // Excluded heavy columns are not loaded ...
        static::assertNull($translated['description'] ?? null, 'description must not be loaded');
        static::assertNull($translated['keywords'] ?? null, 'keywords must not be loaded');
        // ... but the teaser and everything else (incl. customFields) load as usual.
        static::assertNotEmpty($translated['descriptionTeaser'] ?? null);
        static::assertSame(['probe' => 'value'], $translated['customFields'] ?? null, 'customFields must still load');

        // Typed getters work because it stays a real SalesChannelProductEntity.
        static::assertCount(1, $product->getCalculatedPrices(), 'advanced rule prices must survive reduced loading');
        static::assertSame('probe-manufacturer', $product->getManufacturer()?->getTranslation('name'));
    }

    public function testListingLoadsAllDataByDefault(): void
    {
        $this->createData();

        // No config set: reduced loading is opt-in, so full entities incl. description are loaded.
        $product = $this->loadListing()->getEntities()->get($this->ids->get('product0'));
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertNotEmpty($product->getTranslation('description'));
        static::assertNotEmpty($product->getTranslation('descriptionTeaser'));
    }

    public function testListingLoadsAllDataWhenDisabled(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, false);

        $product = $this->loadListing()->getEntities()->get($this->ids->get('product0'));
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertNotEmpty($product->getTranslation('description'));
    }

    public function testExplicitCriteriaFieldsAreNotOverridden(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, true);

        $criteria = new Criteria();
        $criteria->addFields(['id', 'name', 'description']);

        $product = $this->loadListing($criteria)->getEntities()->get($this->ids->get('product0'));

        // An explicit allowlist wins; reduced loading is not applied on top (it would conflict with addFields()).
        static::assertInstanceOf(PartialEntity::class, $product);
        $translated = $product->get('translated');
        static::assertIsArray($translated);
        static::assertNotEmpty($translated['description'] ?? null, 'explicitly requested description must stay loaded');
    }

    public function testCoverMediaIsLoadedAsFullEntity(): void
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
        ]], Context::createDefaultContext());

        static::getContainer()->get('product.repository')->update([[
            'id' => $this->ids->get('product0'),
            'cover' => ['id' => $this->ids->create('product-media'), 'mediaId' => $this->ids->get('media')],
        ]], Context::createDefaultContext());

        $product = $this->loadListing()->getEntities()->get($this->ids->get('product0'));
        static::assertInstanceOf(SalesChannelProductEntity::class, $product);

        $media = $product->getCover()?->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertNotEmpty($media->getUrl());

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
                'keywords' => 'probe keywords',
                'customFields' => ['probe' => 'value'],
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
