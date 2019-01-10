<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\AccountAddress;

use Shopware\Storefront\Page\AccountAddress\AccountAddressPageLoader;
use Shopware\Storefront\Page\AccountAddress\AccountAddressPageRequest;
use Shopware\Storefront\Page\AccountAddress\AccountAddressPageRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountAddressPageRequestResolverTest extends PageRequestTestCase
{
    /**
     * @var AccountAddressPageLoader
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = $this->getContainer()->get(AccountAddressPageRequestResolver::class);
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

        static::assertInstanceOf(AccountAddressPageRequest::class, $request);
    }
}
