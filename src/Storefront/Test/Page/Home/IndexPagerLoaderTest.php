<?php

namespace Shopware\Storefront\Test\Page\Home;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Home\IndexPageLoader;
use Shopware\Storefront\Page\Home\IndexPageRequest;
use Shopware\Storefront\Page\Home\IndexPageStruct;

class IndexPagerLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var IndexPageLoader
     */
    private $pageLoader;

    /**
     * @var CheckoutContextFactory
     */
    private $contextFactory;

    protected function setUp()
    {
        parent::setUp();

        $this->pageLoader = $this->getContainer()->get(IndexPageLoader::class);
        $this->contextFactory = $this->getContainer()->get(CheckoutContextFactory::class);
    }

    public function testPageLoader()
    {
        $request = new IndexPageRequest();

        $context = $this->contextFactory->create(
            Uuid::uuid4()->getHex(),
            Defaults::SALES_CHANNEL
        );

        $page = $this->pageLoader->load($request, $context);

        static::assertInstanceOf(IndexPageStruct::class, $page);
    }
}