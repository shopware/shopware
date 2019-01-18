<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\ProductDetail;

use Shopware\Storefront\Page\Product\ProductDetailPageRequest;
use Shopware\Storefront\Page\Product\ProductDetailPageRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ProductDetailPageRequestTest extends PageRequestTestCase
{
    /**
     * @var ProductDetailPageRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = $this->getContainer()->get(ProductDetailPageRequestResolver::class);
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

        static::assertInstanceOf(ProductDetailPageRequest::class, $request);
    }
}
