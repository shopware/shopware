<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractDeleteAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractUpsertAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\AddressController;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoader;
use Shopware\Storefront\Page\Address\Listing\AddressListingPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddressController::class)]
class AddressControllerTest extends TestCase
{
    private AddressControllerTestClass $controller;

    private MockObject&AddressListingPageLoader $addressListingPageLoader;

    private MockObject&AddressDetailPageLoader $addressDetailPageLoader;

    private MockObject&AccountService $accountService;

    private MockObject&AbstractListAddressRoute $listAddressRoute;

    private MockObject&AbstractUpsertAddressRoute $abstractUpsertAddressRoute;

    private MockObject&AbstractDeleteAddressRoute $deleteAddressRoute;

    private MockObject&AbstractChangeCustomerProfileRoute $changeCustomerProfileRoute;

    protected function setUp(): void
    {
        $this->addressListingPageLoader = $this->createMock(AddressListingPageLoader::class);
        $this->addressDetailPageLoader = $this->createMock(AddressDetailPageLoader::class);
        $this->accountService = $this->createMock(AccountService::class);
        $this->listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $this->abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $this->deleteAddressRoute = $this->createMock(AbstractDeleteAddressRoute::class);
        $this->changeCustomerProfileRoute = $this->createMock(AbstractChangeCustomerProfileRoute::class);

        $this->controller = new AddressControllerTestClass(
            $this->addressListingPageLoader,
            $this->addressDetailPageLoader,
            $this->accountService,
            $this->listAddressRoute,
            $this->abstractUpsertAddressRoute,
            $this->deleteAddressRoute,
            $this->changeCustomerProfileRoute,
        );

        $translator = $this->createMock(TranslatorInterface::class);

        $translator->method('trans')->willReturnCallback(fn (string $key): string => $key);
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('request_stack', new RequestStack());
        $containerBuilder->set('translator', $translator);
        $this->controller->setContainer($containerBuilder);
    }

    public function testAddressBook(): void
    {
        $context = Generator::createSalesChannelContext();
        $request = new Request();
        $dataBag = new RequestDataBag();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $response = $this->controller->addressBook($request, $dataBag, $context, $customer);
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $renderParams = $this->controller->renderStorefrontParameters;

        static::assertArrayHasKey('messages', $renderParams);
        static::assertCount(0, $renderParams['messages']);
        static::assertArrayHasKey('page', $renderParams);
        static::assertArrayNotHasKey('formViolations', $renderParams);
        static::assertArrayNotHasKey('postedData', $renderParams);
    }

    public function testAddressBookWithConstraintViolation(): void
    {
        $context = Generator::createSalesChannelContext();
        $request = new Request();
        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->abstractUpsertAddressRoute
            ->expects(static::once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $response = $this->controller->addressBook($request, $dataBag, $context, $customer);
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $renderParams = $this->controller->renderStorefrontParameters;

        static::assertArrayHasKey('messages', $renderParams);
        static::assertCount(0, $renderParams['messages']);
        static::assertArrayHasKey('page', $renderParams);
        static::assertArrayHasKey('formViolations', $renderParams);
        static::assertArrayHasKey('postedData', $renderParams);
    }

    public function testAddressBookWithException(): void
    {
        $context = Generator::createSalesChannelContext();
        $request = new Request();
        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->abstractUpsertAddressRoute
            ->expects(static::once())
            ->method('upsert')
            ->willThrowException(new \Exception());

        $response = $this->controller->addressBook($request, $dataBag, $context, $customer);
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $renderParams = $this->controller->renderStorefrontParameters;

        static::assertArrayHasKey('success', $renderParams);
        static::assertFalse($renderParams['success']);
        static::assertArrayHasKey('messages', $renderParams);
        static::assertCount(2, $renderParams['messages']);
        static::assertArrayHasKey('type', $renderParams['messages']);
        static::assertArrayHasKey('text', $renderParams['messages']);
        static::assertEquals(AddressControllerTestClass::DANGER, $renderParams['messages']['type']);
        static::assertEquals('error.message-default', $renderParams['messages']['text']);
        static::assertArrayHasKey('page', $renderParams);
        static::assertArrayNotHasKey('formViolations', $renderParams);
        static::assertArrayNotHasKey('postedData', $renderParams);
    }
}

/**
 * @internal
 */
class AddressControllerTestClass extends AddressController
{
    use StorefrontControllerMockTrait;
}
