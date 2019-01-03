<?php

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Pagelet\Navigation\NavigationSubscriber;
use Symfony\Component\HttpFoundation\Request;

class PageRequestResolverTestCase extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var CheckoutContextFactory
     */
    protected $contextFactory;

    protected function setUp()
    {
        parent::setUp();
        $this->contextFactory = $this->getContainer()->get(CheckoutContextFactory::class);
    }

    protected function buildRequest(): Request
    {
        $stack = $this->getContainer()->get('request_stack');

        $httpRequest = Request::create(
            '/test',
            'GET',
            [
                NavigationSubscriber::ROUTE_PARAMETER => 'test',
                NavigationSubscriber::ROUTE_PARAMS_PARAMETER => []
            ]
        );

        $httpRequest->attributes->set(
            PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT,
            $this->contextFactory->create('foo', Defaults::SALES_CHANNEL)
        );

        $stack->push($httpRequest);

        return $httpRequest;
    }
}
