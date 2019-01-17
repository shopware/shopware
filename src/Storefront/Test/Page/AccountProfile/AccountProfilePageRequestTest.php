<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\AccountProfile;

use Shopware\Storefront\Page\AccountProfile\AccountProfilePageRequest;
use Shopware\Storefront\Page\AccountProfile\AccountProfilePageRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountProfilePageRequestTest extends PageRequestTestCase
{
    /**
     * @var AccountProfilePageRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = $this->getContainer()->get(AccountProfilePageRequestResolver::class);
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

        static::assertInstanceOf(AccountProfilePageRequest::class, $request);
    }
}
