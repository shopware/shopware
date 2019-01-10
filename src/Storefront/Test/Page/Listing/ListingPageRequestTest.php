<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Listing;

use Shopware\Storefront\Page\Listing\ListingPageRequest;
use Shopware\Storefront\Page\Listing\ListingPageRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ListingPageRequestTest extends PageRequestTestCase
{
    /**
     * @var ListingPageRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = $this->getContainer()->get(ListingPageRequestResolver::class);
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

        static::assertInstanceOf(ListingPageRequest::class, $request);
    }
}
