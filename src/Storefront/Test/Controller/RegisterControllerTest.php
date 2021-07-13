<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\RegisterController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoader;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class RegisterControllerTest extends TestCase
{
    use MailTemplateTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->accountService = $this->getContainer()->get(AccountService::class);
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $token = Uuid::randomHex();
        $this->salesChannelContext = $salesChannelContextFactory->create($token, Defaults::SALES_CHANNEL);
    }

    public function testGuestRegisterWithRequirePasswordConfirmation(): void
    {
        $container = $this->getContainer();

        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $container->get('customer.repository');

        $config = $this->getContainer()->get(SystemConfigService::class);

        $mock = $this->createMock(SystemConfigService::class);

        $mock->expects(static::any())
            ->method('get')
            ->willReturnCallback(function (string $key) use ($config) {
                if ($key === 'core.loginRegistration.requirePasswordConfirmation') {
                    return true;
                }

                return $config->get($key);
            });

        $registerController = new RegisterController(
            $container->get(AccountLoginPageLoader::class),
            $container->get(RegisterRoute::class),
            $container->get(RegisterConfirmRoute::class),
            $container->get(CartService::class),
            $container->get(CheckoutRegisterPageLoader::class),
            $mock,
            $customerRepository,
            $this->createMock(CustomerGroupRegistrationPageLoader::class),
            $container->get('sales_channel_domain.repository')
        );

        $data = $this->getRegistrationData();

        $request = $this->createRequest();

        $response = $registerController->register($request, $data, $this->salesChannelContext);

        $customers = $this->getContainer()->get(Connection::class)
            ->fetchAll('SELECT * FROM customer WHERE email = :mail', ['mail' => $data->get('email')]);

        static::assertEquals(200, $response->getStatusCode());
        static::assertCount(1, $customers);
    }

    public function testGuestRegister(): void
    {
        $data = $this->getRegistrationData();

        $request = $this->createRequest();

        $response = $this->getContainer()->get(RegisterController::class)->register($request, $data, $this->salesChannelContext);

        $customers = $this->getContainer()->get(Connection::class)
            ->fetchAll('SELECT * FROM customer WHERE email = :mail', ['mail' => $data->get('email')]);

        static::assertEquals(200, $response->getStatusCode());
        static::assertCount(1, $customers);
    }

    public function testRegisterWithDoubleOptIn(): void
    {
        $container = $this->getContainer();

        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $container->get('customer.repository');

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        /** @var CustomerDoubleOptInRegistrationEvent $event */
        $event = null;
        $this->catchEvent(CustomerDoubleOptInRegistrationEvent::class, $event);

        $registerController = new RegisterController(
            $container->get(AccountLoginPageLoader::class),
            $container->get(RegisterRoute::class),
            $container->get(RegisterConfirmRoute::class),
            $container->get(CartService::class),
            $container->get(CheckoutRegisterPageLoader::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(CustomerGroupRegistrationPageLoader::class),
            $container->get('sales_channel_domain.repository')
        );

        $registerController->setContainer($container);

        $data = $this->getRegistrationData(false);
        $data->add(['redirectTo' => 'frontend.checkout.confirm.page']);

        $request = $this->createRequest();

        /** @var RedirectResponse $response */
        $response = $registerController->register($request, $data, $this->salesChannelContext);

        static::assertEquals(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertEquals('/account/register', $response->getTargetUrl());

        /** @var FlashBagInterface $flashbag */
        $flashBag = $container->get('session')->getFlashBag();
        $success = $flashBag->get('success');

        static::assertNotEmpty($success);
        static::assertEquals($container->get('translator')->trans('account.optInRegistrationAlert'), $success[0]);

        static::assertNotEmpty($event);
        static::assertMailEvent(CustomerDoubleOptInRegistrationEvent::class, $event, $this->salesChannelContext);
        static::assertMailRecipientStructEvent($this->getMailRecipientStruct($data->all()), $event);

        static::assertStringEndsWith('&redirectTo=frontend.checkout.confirm.page', $event->getConfirmUrl());
    }

    public function testRegisterWithDoubleOptInDomainChanged(): void
    {
        $container = $this->getContainer();

        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $container->get('customer.repository');

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);
        $systemConfigService->set('core.loginRegistration.doubleOptInDomain', 'https://test.test.com');

        /** @var CustomerDoubleOptInRegistrationEvent $event */
        $event = null;
        $this->catchEvent(CustomerDoubleOptInRegistrationEvent::class, $event);

        $registerController = new RegisterController(
            $container->get(AccountLoginPageLoader::class),
            $container->get(RegisterRoute::class),
            $container->get(RegisterConfirmRoute::class),
            $container->get(CartService::class),
            $container->get(CheckoutRegisterPageLoader::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(CustomerGroupRegistrationPageLoader::class),
            $container->get('sales_channel_domain.repository')
        );

        $registerController->setContainer($container);

        $data = $this->getRegistrationData(false);
        $data->add(['redirectTo' => 'frontend.checkout.confirm.page']);

        $request = $this->createRequest();

        /** @var RedirectResponse $response */
        $response = $registerController->register($request, $data, $this->salesChannelContext);

        static::assertEquals(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertEquals('/account/register', $response->getTargetUrl());

        /** @var FlashBagInterface $flashbag */
        $flashBag = $container->get('session')->getFlashBag();
        $success = $flashBag->get('success');

        static::assertNotEmpty($success);
        static::assertEquals($container->get('translator')->trans('account.optInRegistrationAlert'), $success[0]);

        static::assertNotEmpty($event);
        static::assertMailEvent(CustomerDoubleOptInRegistrationEvent::class, $event, $this->salesChannelContext);
        static::assertMailRecipientStructEvent($this->getMailRecipientStruct($data->all()), $event);

        static::assertStringStartsWith('https://test.test.com', $event->getConfirmUrl());
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', false);
        $systemConfigService->set('core.loginRegistration.doubleOptInDomain', null);
    }

    public function testConfirmRegisterWithRedirectTo(): void
    {
        $container = $this->getContainer();

        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $container->get('customer.repository');

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        /** @var CustomerDoubleOptInRegistrationEvent $event */
        $event = null;
        $this->catchEvent(CustomerDoubleOptInRegistrationEvent::class, $event);

        $registerController = new RegisterController(
            $container->get(AccountLoginPageLoader::class),
            $container->get(RegisterRoute::class),
            $container->get(RegisterConfirmRoute::class),
            $container->get(CartService::class),
            $container->get(CheckoutRegisterPageLoader::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(CustomerGroupRegistrationPageLoader::class),
            $container->get('sales_channel_domain.repository')
        );

        $registerController->setContainer($container);

        $data = $this->getRegistrationData(false);
        $data->add(['redirectTo' => 'frontend.checkout.confirm.page']);

        $request = $this->createRequest();

        /** @var CustomerDoubleOptInRegistrationEvent $event */
        $event = null;
        $this->catchEvent(CustomerDoubleOptInRegistrationEvent::class, $event);

        $registerController->register($request, $data, $this->salesChannelContext);

        $customer = $customerRepository->search(new Criteria([$event->getCustomer()->getId()]), $this->salesChannelContext->getContext());
        $queryData = new QueryDataBag();
        $queryData->set('redirectTo', 'frontend.checkout.confirm.page');
        $queryData->set('hash', $customer->first()->getHash());
        $queryData->set('em', hash('sha1', $event->getCustomer()->getEmail()));

        /** @var RedirectResponse $response */
        $response = $registerController->confirmRegistration($this->salesChannelContext, $queryData);

        static::assertEquals(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertEquals('/checkout/confirm', $response->getTargetUrl());
    }

    private function getMailRecipientStruct(array $customerData): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $customerData['email'] => $customerData['firstName'] . ' ' . $customerData['lastName'],
        ]);
    }

    private function createRequest(): Request
    {
        $request = new Request();
        $request->setSession($this->getContainer()->get('session'));
        $request->request->add(['errorRoute' => 'frontend.checkout.register.page']);
        $request->attributes->add(['_route' => 'frontend.checkout.register.page', SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true]);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');

        $this->getContainer()->get('request_stack')->push($request);

        return $request;
    }

    private function getRegistrationData(?bool $isGuest = true): RequestDataBag
    {
        $data = [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'email' => 'max.mustermann@example.com',
            'emailConfirmation' => 'max.mustermann@example.com',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'storefrontUrl' => 'http://localhost',

            'billingAddress' => [
                'countryId' => $this->getValidCountryId(),
                'street' => 'Musterstrasse 13',
                'zipcode' => '48599',
                'city' => 'Epe',
            ],
        ];

        if ($isGuest) {
            $data['guest'] = '1';
        } else {
            $data['password'] = '12345678';
        }

        return new RequestDataBag($data);
    }
}
