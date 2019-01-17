<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\NavigationSidebar;

use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletRequest;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class NavigationSidebarPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var NavigationSidebarPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new NavigationSidebarPageletRequestResolver(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('request_stack')
        );
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

        static::assertInstanceOf(NavigationSidebarPageletRequest::class, $request);
    }
}
