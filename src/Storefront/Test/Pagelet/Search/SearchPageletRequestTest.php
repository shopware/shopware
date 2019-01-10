<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\Search;

use Shopware\Storefront\Pagelet\Search\SearchPageletRequest;
use Shopware\Storefront\Pagelet\Search\SearchPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class SearchPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var SearchPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new SearchPageletRequestResolver(
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

        static::assertInstanceOf(SearchPageletRequest::class, $request);
    }
}
