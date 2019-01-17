<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Home\ContentHome;

use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Page\ContentHome\ContentHomePageLoader;
use Shopware\Storefront\Page\ContentHome\ContentHomePageRequest;
use Shopware\Storefront\Page\ContentHome\ContentHomePageStruct;
use Shopware\Storefront\Test\Page\PageRequestTestCase;

class ContentHomePagerLoaderTest extends PageRequestTestCase
{
    /**
     * @var CheckoutContextFactory
     */
    protected $contextFactory;
    /**
     * @var ContentHomePageLoader
     */
    private $pageLoader;

    protected function setUp()
    {
        parent::setUp();

        $this->pageLoader = $this->getContainer()->get(ContentHomePageLoader::class);
        $this->contextFactory = $this->getContainer()->get(CheckoutContextFactory::class);
    }

    public function testPageLoader(): void
    {
        $request = new ContentHomePageRequest();

        $context = $this->contextFactory->create(
            Uuid::uuid4()->getHex(),
            Defaults::SALES_CHANNEL
        );
        $request->getHeaderRequest()->getNavigationRequest()->setRoute('/');
        $request->getHeaderRequest()->getNavigationRequest()->setRouteParams(['id' => 'test']);
        $page = $this->pageLoader->load($request, $context);

        static::assertInstanceOf(ContentHomePageStruct::class, $page);
    }
}
