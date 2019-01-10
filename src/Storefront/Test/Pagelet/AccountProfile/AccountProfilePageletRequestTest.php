<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\AccountProfile;

use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletRequest;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountProfilePageletRequestTest extends PageRequestTestCase
{
    /**
     * @var AccountProfilePageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new AccountProfilePageletRequestResolver(
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

        static::assertInstanceOf(AccountProfilePageletRequest::class, $request);
    }
}
