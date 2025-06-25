<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class ConvertGuestRouteTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => TestDefaults::SALES_CHANNEL,
        ]);

        $this->addCountriesToSalesChannel();

        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testConvertGuestSuccess(): void
    {
        // Register as guest
        $this->register(true, 'guest@example.com');

        // Convert guest to registered customer
        $this->browser->request(
            'POST',
            '/store-api/account/convert-guest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(
                ['password' => 'new-password'],
                \JSON_THROW_ON_ERROR,
            ),
        );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        // Verify customer is no longer a guest
        $customer = $this->customerRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('email', 'guest@example.com')),
            Context::createDefaultContext()
        )->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertFalse($customer->getGuest());
    }

    public function testConvertGuestFailsForRegisteredCustomer(): void
    {
        // Register as regular customer
        $this->register(false, 'registered@example.com', 'shopware');

        // Try to convert registered customer (should fail)
        $this->browser
            ->request(
                'POST',
                '/store-api/account/convert-guest',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(
                    ['password' => 'new-password'],
                    \JSON_THROW_ON_ERROR,
                ),
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertStringContainsString('is not a guest', $response['errors'][0]['detail']);
    }

    public function testConvertGuestFailsWithInvalidPassword(): void
    {
        // Register as guest
        $this->register(true, 'guest@example.com');

        // Try to convert with invalid password
        $this->browser
            ->request(
                'POST',
                '/store-api/account/convert-guest',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(
                    ['password' => ''], // Empty password should fail validation
                    \JSON_THROW_ON_ERROR,
                ),
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
    }

    private function register(bool $guest, string $email, ?string $password = null): void
    {
        $data = [
            'guest' => $guest,
            'email' => $email,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'storefrontUrl' => 'http://localhost',
            'billingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'zipcode' => '12345',
                'city' => 'Schöppingen',
                'countryId' => $this->getValidCountryId(),
                'salutationId' => $this->getValidSalutationId(),
            ],
        ];

        if (!$guest && $password !== null) {
            $data['password'] = $password;
        }

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($data, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertNotNull($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }
}
