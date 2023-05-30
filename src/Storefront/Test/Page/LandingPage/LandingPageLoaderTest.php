<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\LandingPage;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\LandingPage\Exception\LandingPageNotFoundException;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Storefront\Page\LandingPage\LandingPage;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Shopware\Storefront\Page\LandingPage\LandingPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('content')]
class LandingPageLoaderTest extends TestCase
{
    use StorefrontPageTestBehaviour;
    use IntegrationTestBehaviour;

    private TestDataCollection $ids;

    public function testLoadWithoutId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('landingPageId', '/landingPageId'));

        $context = $this->createSalesChannelContextWithNavigation();
        $this->getPageLoader()->load(new Request(), $context);
    }

    public function testLoad(): void
    {
        $this->ids = new TestDataCollection();

        $request = new Request([], [], [
            'landingPageId' => $this->ids->get('landing-page'),
        ]);

        $context = $this->createSalesChannelContextWithNavigation();
        $this->ids->set('sales-channel', $context->getSalesChannelId());
        $this->createData();

        /** @var LandingPageLoadedEvent $event */
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
        $this->ids = new TestDataCollection();

        $request = new Request([], [], [
            'landingPageId' => $this->ids->create('landing-page'),
        ]);
        $this->expectExceptionObject(new LandingPageNotFoundException($this->ids->get('landing-page')));

        $context = $this->createSalesChannelContextWithNavigation();
        $this->ids->set('sales-channel', $context->getSalesChannelId());
        $this->createData(false);

        $this->getPageLoader()->load($request, $context);
    }

    public function testLoadWithoutCmsPage(): void
    {
        $this->ids = new TestDataCollection();

        $request = new Request([], [], [
            'landingPageId' => $this->ids->create('landing-page'),
        ]);
        $this->expectExceptionObject(new PageNotFoundException($this->ids->get('landing-page')));

        $context = $this->createSalesChannelContextWithNavigation();
        $this->ids->set('sales-channel', $context->getSalesChannelId());
        $this->createData(true, false);

        $this->getPageLoader()->load($request, $context);
    }

    /**
     * @return LandingPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(LandingPageLoader::class);
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

        $this->getContainer()->get('landing_page.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
