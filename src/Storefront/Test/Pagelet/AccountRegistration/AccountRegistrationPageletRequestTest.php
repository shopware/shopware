<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\AccountRegistration;

use Shopware\Storefront\Pagelet\AccountRegistration\AccountRegistrationPageletRequest;
use Shopware\Storefront\Pagelet\AccountRegistration\AccountRegistrationPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AccountRegistrationPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var AccountRegistrationPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new AccountRegistrationPageletRequestResolver(
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

        static::assertInstanceOf(AccountRegistrationPageletRequest::class, $request);
    }
}
