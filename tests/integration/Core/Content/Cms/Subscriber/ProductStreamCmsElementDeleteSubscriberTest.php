<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cms\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('discovery')]
class ProductStreamCmsElementDeleteSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductStreamCollection>
     */
    private EntityRepository $productStreamRepository;

    /**
     * @var EntityRepository<CmsPageCollection>
     */
    private EntityRepository $cmsPageRepository;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<LandingPageCollection>
     */
    private EntityRepository $landingPageRepository;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
        $this->productStreamRepository = static::getContainer()->get('product_stream.repository');
        $this->cmsPageRepository = static::getContainer()->get('cms_page.repository');
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->landingPageRepository = static::getContainer()->get('landing_page.repository');
        $this->salesChannelRepository = static::getContainer()->get('sales_channel.repository');
    }

    public function testProductStreamDeleteIsRestrictedWhenUsedInCmsProductSliders(): void
    {
        $ids = new IdsCollection();
        $productStreamId = $this->createProductStream($ids);
        $cmsSlotId = $this->createCmsPageWithProductSlider($ids, $productStreamId);
        $categoryId = $this->createCategoryWithSlotConfig($ids, $productStreamId);
        $productId = $this->createProductWithSlotConfig($ids, $productStreamId);
        $landingPageId = $this->createLandingPageWithSlotConfig($ids, $productStreamId);
        $salesChannelId = $this->updateSalesChannelHomeSlotConfig($ids, $productStreamId);

        try {
            $this->productStreamRepository->delete([['id' => $productStreamId]], $this->context);
        } catch (RestrictDeleteViolationException $exception) {
            static::assertSame('FRAMEWORK__DELETE_RESTRICTED', $exception->getErrorCode());
            static::assertSame([
                CmsSlotDefinition::ENTITY_NAME => [$cmsSlotId],
                CategoryDefinition::ENTITY_NAME => [$categoryId],
                ProductDefinition::ENTITY_NAME => [$productId],
                LandingPageDefinition::ENTITY_NAME => [$landingPageId],
                SalesChannelDefinition::ENTITY_NAME => [$salesChannelId],
            ], $exception->getRestrictions()[0]->getRestrictions());

            return;
        }

        static::fail('Expected product stream deletion to be restricted.');
    }

    public function testProductStreamDeleteIgnoresStaticCmsProductSliderValues(): void
    {
        $ids = new IdsCollection();
        $productStreamId = $this->createProductStream($ids);
        $this->createCmsPageWithProductSlider($ids, $productStreamId, 'static');

        $this->productStreamRepository->delete([['id' => $productStreamId]], $this->context);

        $existingProductStreamId = $this->productStreamRepository
            ->searchIds(new Criteria([$productStreamId]), $this->context)
            ->firstId();

        static::assertNull($existingProductStreamId);
    }

    private function createProductStream(IdsCollection $ids): string
    {
        $productStreamId = $ids->create('product-stream');
        $this->productStreamRepository->create([[
            'id' => $productStreamId,
            'name' => 'Product stream',
        ]], $this->context);

        return $productStreamId;
    }

    private function createCmsPageWithProductSlider(IdsCollection $ids, string $productStreamId, string $source = 'product_stream'): string
    {
        $cmsSlotId = $ids->create('cms-slot');
        $this->cmsPageRepository->create([[
            'id' => $ids->create('cms-page'),
            'name' => 'Test page',
            'type' => 'landingpage',
            'sections' => [[
                'id' => $ids->create('cms-section'),
                'type' => 'default',
                'position' => 0,
                'blocks' => [[
                    'id' => $ids->create('cms-block'),
                    'type' => 'product-slider',
                    'position' => 0,
                    'sectionPosition' => 'main',
                    'slots' => [[
                        'id' => $cmsSlotId,
                        'type' => 'product-slider',
                        'slot' => 'content',
                        'config' => self::productStreamSliderConfig($productStreamId, $source),
                    ]],
                ]],
            ]],
        ]], $this->context);

        return $cmsSlotId;
    }

    private function createCategoryWithSlotConfig(IdsCollection $ids, string $productStreamId): string
    {
        $categoryId = $ids->create('category');
        $this->categoryRepository->create([[
            'id' => $categoryId,
            'name' => 'Category',
            'cmsPageId' => $ids->get('cms-page'),
            'slotConfig' => self::slotConfig($ids, $productStreamId),
        ]], $this->context);

        return $categoryId;
    }

    private function createProductWithSlotConfig(IdsCollection $ids, string $productStreamId): string
    {
        $productId = $ids->create('product');
        $this->productRepository->create([[
            'id' => $productId,
            'name' => 'Product',
            'productNumber' => 'product-' . $productId,
            'stock' => 1,
            'tax' => [
                'id' => $ids->create('tax'),
                'name' => 'Tax',
                'taxRate' => 19,
            ],
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 100,
                'net' => 100,
                'linked' => false,
            ]],
            'cmsPageId' => $ids->get('cms-page'),
            'slotConfig' => self::slotConfig($ids, $productStreamId),
        ]], $this->context);

        return $productId;
    }

    private function createLandingPageWithSlotConfig(IdsCollection $ids, string $productStreamId): string
    {
        $landingPageId = $ids->create('landing-page');
        $this->landingPageRepository->create([[
            'id' => $landingPageId,
            'name' => 'Landing page',
            'url' => 'landing-page-' . $landingPageId,
            'active' => true,
            'cmsPageId' => $ids->get('cms-page'),
            'salesChannels' => [[
                'id' => $this->getSalesChannelId(),
            ]],
            'slotConfig' => self::slotConfig($ids, $productStreamId),
        ]], $this->context);

        return $landingPageId;
    }

    private function updateSalesChannelHomeSlotConfig(IdsCollection $ids, string $productStreamId): string
    {
        $salesChannelId = $this->getSalesChannelId();
        $this->salesChannelRepository->update([[
            'id' => $salesChannelId,
            'homeCmsPageId' => $ids->get('cms-page'),
            'homeSlotConfig' => self::slotConfig($ids, $productStreamId),
        ]], $this->context);

        return $salesChannelId;
    }

    private function getSalesChannelId(): string
    {
        $salesChannelId = $this->salesChannelRepository
            ->searchIds(new Criteria(), $this->context)
            ->firstId();

        static::assertIsString($salesChannelId);

        return $salesChannelId;
    }

    /**
     * @return array<string, mixed>
     */
    private static function slotConfig(IdsCollection $ids, string $productStreamId): array
    {
        return [
            $ids->get('cms-slot') => self::productStreamSliderConfig($productStreamId),
        ];
    }

    /**
     * @return array<string, array{source: string, value: string}>
     */
    private static function productStreamSliderConfig(string $productStreamId, string $source = 'product_stream'): array
    {
        return [
            'products' => [
                'source' => $source,
                'value' => $productStreamId,
            ],
        ];
    }
}
