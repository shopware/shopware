<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\AccountAddress;

use Shopware\Storefront\Pagelet\AccountAddress\AccountAddressPageletRequest;
use Shopware\Storefront\Pagelet\AccountAddress\AccountAddressPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountAddressPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var AccountAddressPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new AccountAddressPageletRequestResolver(
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

        static::assertInstanceOf(AccountAddressPageletRequest::class, $request);
    }
}
