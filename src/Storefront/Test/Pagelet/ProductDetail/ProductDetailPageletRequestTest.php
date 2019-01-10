<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\ProductDetail;

use Shopware\Storefront\Pagelet\ProductDetail\ProductDetailPageletRequest;
use Shopware\Storefront\Pagelet\ProductDetail\ProductDetailPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ProductDetailPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var ProductDetailPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new ProductDetailPageletRequestResolver(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('request_stack')
        );
    }

    public function testResolveArgument()
    {
        $httpRequest = $this->buildRequest();
        $httpRequest->attributes->set('id', 'test');
        $request = $this->requestResolver->resolve(
            $httpRequest,
            new ArgumentMetadata('foo', self::class, false, false, null)
        );

        $request = iterator_to_array($request);
        $request = array_pop($request);

        static::assertInstanceOf(ProductDetailPageletRequest::class, $request);
    }
}
