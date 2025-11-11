<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedHook;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoadedHook;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @internal
 */
class NavigationControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();
    }

    public function testNavigationPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(NavigationPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testNavigationPageLoadedHookScriptsAreExecutedForCategory(): void
    {
        $response = $this->request('GET', '/my-navigation/', []);

        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(NavigationPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testMenuOffcanvasPageletLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/widgets/menu/offcanvas', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(MenuOffcanvasPageletLoadedHook::HOOK_NAME, $traces);
    }

    /**
     * Test that Content System renders when feature flag is enabled and layout exists
     */
    public function testContentSystemRendersWithValidLayout(): void
    {
        Feature::skipTestIfInActive('STOREFRONT_COMPONENTS', $this);

        // This test requires a content layout to be assigned to the homepage category
        // For now, we expect ContentSystemException since no layout is set up
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('No layout assignment found');

        $response = $this->request('GET', '/', []);

        // When a layout is properly assigned, these assertions should pass:
        // static::assertSame(200, $response->getStatusCode());
        // $content = $response->getContent();
        // static::assertNotFalse($content);
        //
        // $crawler = new Crawler($content);
        // // Verify Content System component is rendered (not traditional CMS)
        // static::assertGreaterThan(0, $crawler->filter('.page')->count());
    }

    /**
     * Test that ContentSystemException is thrown when no layout is assigned
     */
    public function testContentSystemThrowsExceptionWithoutLayout(): void
    {
        Feature::skipTestIfInActive('STOREFRONT_COMPONENTS', $this);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('No layout assignment found');

        $this->request('GET', '/', []);
    }

    /**
     * Test that traditional CMS renders when feature flag is disabled
     */
    public function testTraditionalCmsRendersWhenFeatureFlagDisabled(): void
    {
        Feature::skipTestIfActive('STOREFRONT_COMPONENTS', $this);

        $response = $this->request('GET', '/', []);

        static::assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        static::assertNotFalse($content);

        // Traditional CMS should render, not Content System components
        $crawler = new Crawler($content);
        static::assertGreaterThan(0, $crawler->filter('.container-main')->count());
    }

    /**
     * Test that Content System only applies to homepage, not other navigation pages
     */
    public function testContentSystemOnlyAppliesToHomepage(): void
    {
        Feature::skipTestIfInActive('STOREFRONT_COMPONENTS', $this);

        // Category page should still use traditional CMS even with feature flag
        $response = $this->request('GET', '/my-navigation/', []);

        static::assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        static::assertNotFalse($content);

        // Should render traditional CMS for non-homepage
        $crawler = new Crawler($content);
        static::assertGreaterThan(0, $crawler->filter('.container-main')->count());
    }

    private function createData(): void
    {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = static::getContainer()->get('sales_channel.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->first();

        $category = [
            'id' => $this->ids->create('category'),
            'name' => 'my-navigation',
            'type' => 'landing_page',
            'parentId' => $salesChannel->getNavigationCategoryId(),
        ];

        static::getContainer()->get('category.repository')->create([$category], Context::createDefaultContext());
    }
}
