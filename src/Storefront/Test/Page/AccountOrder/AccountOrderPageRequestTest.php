<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\AccountOrder;

use Shopware\Storefront\Page\AccountOrder\AccountOrderPageRequest;
use Shopware\Storefront\Page\AccountOrder\AccountOrderPageRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountOrderPageRequestTest extends PageRequestTestCase
{
    /**
     * @var AccountOrderPageRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = $this->getContainer()->get(AccountOrderPageRequestResolver::class);
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

        static::assertInstanceOf(AccountOrderPageRequest::class, $request);
    }
}
