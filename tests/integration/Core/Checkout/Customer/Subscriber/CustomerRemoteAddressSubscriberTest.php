<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerRemoteAddressSubscriberTest extends TestCase
{
    use LineItemTestFixtureBehaviour;
    use SalesChannelFunctionalTestBehaviour;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => TestDefaults::SALES_CHANNEL,
            'languages' => [],
        ]);
        $this->assignSalesChannelContext($this->browser);
    }

    public function testUpdateRemoteAddressByLogin(): void
    {
        $email = Uuid::randomHex() . '@example.com';

        $customerId = $this->login($email);

        $remoteAddress = $this->browser->getRequest()->getClientIp();

        $customer = $this->fetchCustomerById($customerId);

        static::assertNotSame($customer->getRemoteAddress(), $remoteAddress);
        static::assertSame($customer->getRemoteAddress(), IpUtils::anonymize((string) $remoteAddress));
    }

    private function login(string $email): string
    {
        $customerId = $this->createCustomer($email);

        $this->browser->request('POST', '/store-api/account/login', [
            'username' => $email,
            'password' => 'shopware',
        ]);

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        return $customerId;
    }

    private function createCustomer(string $email): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $this->getContainer()->get('customer.repository')->create([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function fetchCustomerById(string $customerId): CustomerEntity
    {
        /** @var CustomerEntity|null $customer */
        $customer = $this->getContainer()
            ->get('customer.repository')
            ->search(new Criteria([$customerId]), Context::createDefaultContext())
            ->first();

        static::assertNotNull($customer);

        return $customer;
    }
}
