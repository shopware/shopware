<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class CustomerChangePasswordSubscriberTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testClearLegacyWhenUserChangePassword(): void
    {
        $email = Uuid::randomHex() . '@shopware.com';
        $password = 'ThisIsNewPassword';

        $newPassword = Uuid::randomHex();
        $customerId = $this->createCustomer($email, $password);

        $context = Context::createDefaultContext();

        $this->getBrowser()->request(
            'PATCH',
            '/api/customer/' . $customerId,
            ['password' => $newPassword]
        );

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $customerId));

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search($criteria, $context)->first();

        static::assertNotNull($customer->getPassword());
        static::assertNull($customer->getLegacyPassword());
        static::assertNull($customer->getLegacyEncoder());

        $this->loginUser($email, $newPassword);
    }

    public function testNotClearLegacyDataWhenUserNotChangedPassword(): void
    {
        $email = Uuid::randomHex() . '@shopware.com';
        $password = 'ThisIsNewPassword';

        $customerId = $this->createCustomer($email, $password);
        $context = Context::createDefaultContext();

        $this->getBrowser()->request(
            'PATCH',
            '/api/customer/' . $customerId,
            ['firstName' => 'Test']
        );

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $customerId));

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search($criteria, $context)->first();

        static::assertNull($customer->getPassword());
        static::assertNotNull($customer->getLegacyPassword());
        static::assertNotNull($customer->getLegacyEncoder());

        $this->loginUser($email, $password);
    }

    private function loginUser(string $email, string $password): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);
    }

    private function createCustomer(string $email, string $password): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->getContainer()->get('customer.repository')->create([
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schoöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => null,
                'legacyPassword' => md5($password),
                'legacyEncoder' => 'Md5',
                'firstName' => 'encryption',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], Context::createDefaultContext());

        return $customerId;
    }
}
