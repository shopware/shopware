<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Analytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Analytics\AnalyticsCategoryPathLoader;
use Shopware\Storefront\Framework\Analytics\AnalyticsCategoryPathSubscriber;
use Shopware\Storefront\Page\LandingPage\LandingPage;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AnalyticsCategoryPathSubscriber::class)]
class AnalyticsCategoryPathSubscriberTest extends TestCase
{
    private const NAVIGATION_ID = 'navigation-root';

    public function testRunsAfterTheAnalyticsSettingsWereLoaded(): void
    {
        static::assertSame(
            [StorefrontRenderEvent::class => ['addCategoryPaths', 1000]],
            AnalyticsCategoryPathSubscriber::getSubscribedEvents()
        );
    }

    public function testAddsThePathsToTheProductBoxesOfTheHomepage(): void
    {
        $slider = $this->product('slider-product');
        $box = $this->product('box-product');
        $withoutPath = $this->product('product-without-path');

        $loader = $this->createMock(AnalyticsCategoryPathLoader::class);
        $loader->expects($this->once())->method('load')
            ->with(static::callback(static fn (array $products) => array_map(static fn (SalesChannelProductEntity $product) => $product->getId(), $products) === ['slider-product', 'product-without-path', 'box-product']))
            ->willReturn([
                'slider-product' => ['Clothing', 'Shirts'],
                'box-product' => ['Shoes'],
            ]);

        $page = $this->homepage($this->cmsPage(
            $this->sliderData($slider, $withoutPath),
            $this->boxData($box),
        ));

        (new AnalyticsCategoryPathSubscriber($loader))->addCategoryPaths($this->event('frontend.home.page', $page));

        static::assertEquals(new ArrayStruct(['Clothing', 'Shirts']), $slider->getExtension(AnalyticsCategoryPathSubscriber::EXTENSION));
        static::assertEquals(new ArrayStruct(['Shoes']), $box->getExtension(AnalyticsCategoryPathSubscriber::EXTENSION));
        static::assertNull($withoutPath->getExtension(AnalyticsCategoryPathSubscriber::EXTENSION));
    }

    public function testAddsThePathsOnALandingPage(): void
    {
        $product = $this->product('product-1');

        $loader = $this->createMock(AnalyticsCategoryPathLoader::class);
        $loader->expects($this->once())->method('load')->willReturn(['product-1' => ['Sale']]);

        $landingPage = new LandingPageEntity();
        $landingPage->setCmsPage($this->cmsPage($this->boxData($product)));
        $page = new LandingPage();
        $page->setLandingPage($landingPage);

        (new AnalyticsCategoryPathSubscriber($loader))->addCategoryPaths($this->event('frontend.landing.page', $page));

        static::assertEquals(new ArrayStruct(['Sale']), $product->getExtension(AnalyticsCategoryPathSubscriber::EXTENSION));
    }

    public function testLoadsNothingWithoutActiveAnalytics(): void
    {
        $loader = $this->createMock(AnalyticsCategoryPathLoader::class);
        $loader->expects($this->never())->method('load');

        $page = $this->homepage($this->cmsPage($this->boxData($this->product('product-1'))));

        $inactive = new SalesChannelAnalyticsEntity();
        $inactive->setActive(false);

        $subscriber = new AnalyticsCategoryPathSubscriber($loader);
        $subscriber->addCategoryPaths($this->eventWithAnalytics('frontend.home.page', $page, $inactive));
        $subscriber->addCategoryPaths($this->eventWithAnalytics('frontend.home.page', $page, null));
    }

    /**
     * A homepage can show its products through a listing element instead of sliders.
     */
    public function testAddsThePathsToAListingOnTheHomepage(): void
    {
        $product = $this->product('listed-product');

        $loader = $this->createMock(AnalyticsCategoryPathLoader::class);
        $loader->expects($this->once())->method('load')->willReturn(['listed-product' => ['Guitars']]);

        $listing = new ProductListingStruct();
        $listing->setListing(new EntitySearchResult(
            $product->getApiAlias(),
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        (new AnalyticsCategoryPathSubscriber($loader))->addCategoryPaths($this->event('frontend.home.page', $this->homepage($this->cmsPage($listing))));

        static::assertEquals(new ArrayStruct(['Guitars']), $product->getExtension(AnalyticsCategoryPathSubscriber::EXTENSION));
    }

    /**
     * Extensions add Shopping Experience elements with their own data structs, so the products are
     * found wherever an element keeps them, here two levels down in a plugin struct.
     */
    public function testFindsTheProductsOfAnExtensionElement(): void
    {
        $product = $this->product('plugin-product');

        $loader = $this->createMock(AnalyticsCategoryPathLoader::class);
        $loader->expects($this->once())->method('load')
            ->with([$product])
            ->willReturn(['plugin-product' => ['Drums']]);

        $pluginData = new ArrayStruct([
            'headline' => 'Bestsellers',
            'tabs' => [['products' => new ProductCollection([$product])]],
        ]);

        (new AnalyticsCategoryPathSubscriber($loader))->addCategoryPaths($this->event('frontend.home.page', $this->homepage($this->cmsPage($pluginData))));

        static::assertEquals(new ArrayStruct(['Drums']), $product->getExtension(AnalyticsCategoryPathSubscriber::EXTENSION));
    }

    /**
     * The products of other entities, such as the cross selling of a product, are not on the page.
     */
    public function testDoesNotWalkIntoEntities(): void
    {
        $nested = $this->product('nested-product');
        $category = new CategoryEntity();
        $category->setId('category');
        $category->addExtension('products', new ProductCollection([$nested]));

        $loader = $this->createMock(AnalyticsCategoryPathLoader::class);
        $loader->expects($this->never())->method('load');

        (new AnalyticsCategoryPathSubscriber($loader))->addCategoryPaths($this->event('frontend.home.page', $this->homepage($this->cmsPage(new ArrayStruct(['category' => $category])))));
    }

    /**
     * The root category can also be opened through the navigation route, which renders the homepage.
     */
    public function testAddsThePathsToTheHomepageUnderTheNavigationRoute(): void
    {
        $product = $this->product('product-1');

        $loader = $this->createMock(AnalyticsCategoryPathLoader::class);
        $loader->expects($this->once())->method('load')->willReturn(['product-1' => ['Sale']]);

        $page = $this->homepage($this->cmsPage($this->boxData($product)));

        (new AnalyticsCategoryPathSubscriber($loader))->addCategoryPaths($this->event('frontend.navigation.page', $page));

        static::assertEquals(new ArrayStruct(['Sale']), $product->getExtension(AnalyticsCategoryPathSubscriber::EXTENSION));
    }

    /**
     * A category page reports the category of its breadcrumb, so its product boxes load nothing.
     */
    public function testLoadsNothingOnACategoryPage(): void
    {
        $loader = $this->createMock(AnalyticsCategoryPathLoader::class);
        $loader->expects($this->never())->method('load');

        $page = new NavigationPage();
        $page->setNavigationId('some-category');
        $page->setCmsPage($this->cmsPage($this->boxData($this->product('product-1'))));

        (new AnalyticsCategoryPathSubscriber($loader))->addCategoryPaths($this->event('frontend.navigation.page', $page));
    }

    public function testLoadsNothingForAPageWithoutProducts(): void
    {
        $loader = $this->createMock(AnalyticsCategoryPathLoader::class);
        $loader->expects($this->never())->method('load');

        $subscriber = new AnalyticsCategoryPathSubscriber($loader);
        $subscriber->addCategoryPaths($this->event('frontend.home.page', $this->homepage(null)));
        $subscriber->addCategoryPaths($this->event('frontend.landing.page', new LandingPage()));
        $subscriber->addCategoryPaths($this->event('frontend.home.page', null));
    }

    private function event(string $route, mixed $page): StorefrontRenderEvent
    {
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setActive(true);

        return $this->eventWithAnalytics($route, $page, $analytics);
    }

    private function eventWithAnalytics(string $route, mixed $page, ?SalesChannelAnalyticsEntity $analytics): StorefrontRenderEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId(self::NAVIGATION_ID);

        return new StorefrontRenderEvent(
            '@Storefront/storefront/page/content/index.html.twig',
            ['page' => $page, 'storefrontAnalytics' => $analytics],
            $request,
            $context
        );
    }

    private function homepage(?CmsPageEntity $cmsPage): NavigationPage
    {
        $page = new NavigationPage();
        $page->setNavigationId(self::NAVIGATION_ID);

        if ($cmsPage !== null) {
            $page->setCmsPage($cmsPage);
        }

        return $page;
    }

    private function product(string $id): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($id);

        return $product;
    }

    private function sliderData(SalesChannelProductEntity ...$products): ProductSliderStruct
    {
        $slider = new ProductSliderStruct();
        $slider->setProducts(new ProductCollection($products));

        return $slider;
    }

    private function boxData(SalesChannelProductEntity $product): ProductBoxStruct
    {
        $box = new ProductBoxStruct();
        $box->setProduct($product);

        return $box;
    }

    private function cmsPage(Struct ...$slotData): CmsPageEntity
    {
        $slots = new CmsSlotCollection();
        foreach ($slotData as $index => $data) {
            $slot = new CmsSlotEntity();
            $slot->setId('slot-' . $index);
            $slot->setSlot('content-' . $index);
            $slot->setData($data);
            $slots->add($slot);
        }

        // a slot without product data, such as a text, is skipped
        $text = new CmsSlotEntity();
        $text->setId('text-slot');
        $text->setSlot('text');
        $text->setData(new ArrayStruct(['content' => 'Welcome']));
        $slots->add($text);

        $block = new CmsBlockEntity();
        $block->setId('block');
        $block->setSlots($slots);

        $section = new CmsSectionEntity();
        $section->setId('section');
        $section->setBlocks(new CmsBlockCollection([$block]));

        $page = new CmsPageEntity();
        $page->setSections(new CmsSectionCollection([$section]));

        return $page;
    }
}
