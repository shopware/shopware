<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\AccountPaymentMethod;

use Shopware\Storefront\Page\AccountPaymentMethod\AccountPaymentMethodPageRequest;
use Shopware\Storefront\Page\AccountPaymentMethod\AccountPaymentMethodPageRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountPaymentMethodPageRequestTest extends PageRequestTestCase
{
    /**
     * @var AccountPaymentMethodPageRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = $this->getContainer()->get(AccountPaymentMethodPageRequestResolver::class);
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

        static::assertInstanceOf(AccountPaymentMethodPageRequest::class, $request);
    }
}
