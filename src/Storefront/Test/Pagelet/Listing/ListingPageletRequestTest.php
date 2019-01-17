<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\Listing;

use Shopware\Storefront\Pagelet\Listing\ListingPageletRequest;
use Shopware\Storefront\Pagelet\Listing\ListingPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ListingPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var ListingPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new ListingPageletRequestResolver(
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

        static::assertInstanceOf(ListingPageletRequest::class, $request);
    }
}
