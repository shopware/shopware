<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page\LandingPage;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Page\LandingPage\LandingPage;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Shopware\Storefront\Page\LandingPage\LandingPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class LandingPageLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private IdsCollection $ids;

    public function testLoadWithoutId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('landingPageId', '/landingPageId'));

        $context = $this->createSalesChannelContextWithNavigation();
        $this->getPageLoader()->load(new Request(), $context);
    }

    public function testLoad(): void
    {
        $this->ids = new IdsCollection();

        $request = new Request([], [], [
            'landingPageId' => $this->ids->get('landing-page'),
        ]);

        $context = $this->createSalesChannelContextWithNavigation();
        $this->ids->set('sales-channel', $context->getSalesChannelId());
        $this->createData();

        $event = null;
        $this->catchEvent(LandingPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(LandingPage::class, $page);

        static::assertInstanceOf(LandingPageEntity::class, $page->getLandingPage());
        static::assertInstanceOf(CmsPageEntity::class, $page->getLandingPage()->getCmsPage());
        static::assertSame($this->ids->get('cms-page'), $page->getLandingPage()->getCmsPage()->getId());

        self::assertPageEvent(LandingPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testLoadWithInactiveLandingPage(): void
    {
        $this->ids = new IdsCollection();

        $request = new Request([], [], [
            'landingPageId' => $this->ids->create('landing-page'),
        ]);
        $this->expectExceptionObject(LandingPageException::notFound($this->ids->get('landing-page')));

        $context = $this->createSalesChannelContextWithNavigation();
        $this->ids->set('sales-channel', $context->getSalesChannelId());
        $this->createData(false);

        $this->getPageLoader()->load($request, $context);
    }

    public function testLoadWithoutCmsPage(): void
    {
        $this->ids = new IdsCollection();
        $landingPageId = $this->ids->create('landing-page');

        $request = new Request([], [], [
            'landingPageId' => $landingPageId,
        ]);

        $expectedException = LandingPageException::notFound($landingPageId);

        // @deprecated tag:v6.8.0 - remove this if block
        if (!Feature::isActive('v6.8.0.0')) {
            $expectedException = new PageNotFoundException($landingPageId);
        }

        $this->expectExceptionObject($expectedException);

        $context = $this->createSalesChannelContextWithNavigation();
        $this->ids->set('sales-channel', $context->getSalesChannelId());
        $this->createData(true, false);

        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesHaveCanonicalTag(): void
    {
        $this->ids = new IdsCollection();

        $request = new Request([], [], [
            'landingPageId' => $this->ids->get('landing-page'),
        ]);

        $context = $this->createSalesChannelContextWithNavigation();
        $this->ids->set('sales-channel', $context->getSalesChannelId());
        $this->createData();

        $seoUrlHandler = static::getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);

        $event = null;
        $this->catchEvent(LandingPageLoadedEvent::class, $event);

        $metaInformation = $this->getPageLoader()->load($request, $context)->getMetaInformation();
        static::assertNotNull($metaInformation);
        $meta = $metaInformation->getVars();
        $canonical = $meta['canonical'];

        static::assertIsString($canonical);
        static::assertNotSame('', $canonical);

        $seoUrl = $seoUrlHandler->replace($canonical, $request->getHost(), $context);

        static::assertSame('/myUrl', $seoUrl);
    }

    public function testItDoesHaveGenericMetaInformation(): void
    {
        $locale = 'en-GB';

        $this->ids = new IdsCollection();

        $request = new Request([], [], [
            'landingPageId' => $this->ids->get('landing-page'),
            SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE => $locale,
        ]);

        $context = $this->createSalesChannelContextWithNavigation();
        $this->ids->set('sales-channel', $context->getSalesChannelId());
        $this->createData();

        $event = null;
        $this->catchEvent(LandingPageLoadedEvent::class, $event);

        $metaInformation = $this->getPageLoader()->load($request, $context)->getMetaInformation();

        static::assertNotNull($metaInformation);
        static::assertSame('15 days', $metaInformation->getRevisit());
        static::assertSame('index,follow', $metaInformation->getRobots());
        static::assertSame($locale, $metaInformation->getXmlLang());
    }

    /**
     * @return LandingPageLoader
     */
    protected function getPageLoader()
    {
        return static::getContainer()->get(LandingPageLoader::class);
    }

    private function createData(bool $isActive = true, bool $withCmsPage = true): void
    {
        $data = [
            'id' => $this->ids->create('landing-page'),
            'name' => 'Test',
            'url' => 'myUrl',
            'active' => $isActive,
            'salesChannels' => [
                [
                    'id' => $this->ids->get('sales-channel'),
                ],
            ],
            'cmsPage' => [
                'id' => $this->ids->create('cms-page'),
                'type' => 'product_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'product-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'product-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (!$withCmsPage) {
            unset($data['cmsPage']);
        }

        static::getContainer()->get('landing_page.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
