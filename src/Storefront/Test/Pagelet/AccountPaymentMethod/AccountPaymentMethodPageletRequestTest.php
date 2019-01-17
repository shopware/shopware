<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\AccountPaymentMethod;

use Shopware\Storefront\Pagelet\AccountPaymentMethod\AccountPaymentMethodPageletRequest;
use Shopware\Storefront\Pagelet\AccountPaymentMethod\AccountPaymentMethodPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountPaymentMethodPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var AccountPaymentMethodPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new AccountPaymentMethodPageletRequestResolver(
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

        static::assertInstanceOf(AccountPaymentMethodPageletRequest::class, $request);
    }
}
