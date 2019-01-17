<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\AccountOrder;

use Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletRequest;
use Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountOrderPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var AccountOrderPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new AccountOrderPageletRequestResolver(
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

        static::assertInstanceOf(AccountOrderPageletRequest::class, $request);
    }
}
