<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\AccountLogin;

use Shopware\Storefront\Page\AccountLogin\AccountLoginPageRequest;
use Shopware\Storefront\Page\AccountLogin\AccountLoginPageRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountLoginPageRequestTest extends PageRequestTestCase
{
    /**
     * @var AccountLoginPageRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = $this->getContainer()->get(AccountLoginPageRequestResolver::class);
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

        static::assertInstanceOf(AccountLoginPageRequest::class, $request);
    }
}
