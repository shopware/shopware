<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\CheckoutPaymentMethod;

use Shopware\Storefront\Pagelet\CheckoutPaymentMethod\CheckoutPaymentMethodPageletRequest;
use Shopware\Storefront\Pagelet\CheckoutPaymentMethod\CheckoutPaymentMethodPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CheckoutPaymentMethodPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var CheckoutPaymentMethodPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new CheckoutPaymentMethodPageletRequestResolver(
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

        static::assertInstanceOf(CheckoutPaymentMethodPageletRequest::class, $request);
    }
}
