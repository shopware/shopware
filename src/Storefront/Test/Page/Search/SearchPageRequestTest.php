<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Search;

use Shopware\Storefront\Page\Search\SearchPageRequest;
use Shopware\Storefront\Page\Search\SearchPageRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class SearchPageRequestTest extends PageRequestTestCase
{
    /**
     * @var SearchPageRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = $this->getContainer()->get(SearchPageRequestResolver::class);
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

        static::assertInstanceOf(SearchPageRequest::class, $request);
    }
}
