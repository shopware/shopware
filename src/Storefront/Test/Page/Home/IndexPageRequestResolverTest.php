<?php

namespace Shopware\Storefront\Test\Page\Home;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Page\Home\IndexPageRequest;
use Shopware\Storefront\Page\Home\IndexPageRequestResolver;
use Shopware\Storefront\Pagelet\Navigation\NavigationSubscriber;
use Shopware\Storefront\Test\Page\PageRequestResolverTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class IndexPageRequestResolverTest extends PageRequestResolverTestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var IndexPageRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = $this->getContainer()->get(IndexPageRequestResolver::class);
    }

    public function testResolveArgument()
    {
        $httpRequest = $this->buildRequest();

        $request = $this->requestResolver->resolve(
            $httpRequest,
            new ArgumentMetadata('foo', self::class, false, false, null)
        );

        $request = iterator_to_array($request);
        $request = array_pop($request);

        static::assertInstanceOf(IndexPageRequest::class, $request);
    }
}