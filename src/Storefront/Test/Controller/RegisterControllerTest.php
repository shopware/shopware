<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\RegisterController;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Symfony\Component\HttpFoundation\Request;

class RegisterControllerTest extends TestCase
{
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
            $this->accountService,
            $container->get(AccountRegistrationService::class),
            $container->get(CartService::class),
            $container->get(CheckoutRegisterPageLoader::class),
            $mock,
            $customerRepository
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

    private function createRequest(): Request
    {
        $request = new Request();
        $request->setSession($this->getContainer()->get('session'));
        $request->request->add(['errorRoute' => 'frontend.checkout.register.page']);
        $request->attributes->add(['_route' => 'frontend.checkout.register.page', SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true]);

        $this->getContainer()->get('request_stack')->push($request);

        return $request;
    }

    private function getRegistrationData(): RequestDataBag
    {
        $data = [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'email' => 'max.mustermann@example.com',
            'emailConfirmation' => 'max.mustermann@example.com',
            'guest' => '1',
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

        return new RequestDataBag($data);
    }
}
