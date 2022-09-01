<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 * @covers \Shopware\Storefront\Controller\NewsletterController
 */
class NewsletterControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use StorefrontControllerTestBehaviour;

    /**
     * @var array<mixed>
     */
    private array $customerData = [];

    private SystemConfigService $systemConfig;

    public function setUp(): void
    {
        $this->systemConfig = $this->getContainer()->get(SystemConfigService::class);
        static::assertNotNull($this->systemConfig);
        $this->systemConfig->set('core.newsletter.doubleOptIn', false);
    }

    public function testRegisterNewsletterForCustomerDirect(): void
    {
        $browser = $this->login();
        $data = [
            'option' => 'direct',
        ];

        $browser->request(
            'POST',
            '/widgets/account/newsletter',
            $this->tokenize('frontend.account.newsletter', $data)
        );

        $response = $browser->getResponse();

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());

        $repo = $this->getContainer()->get('newsletter_recipient.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, Context::createDefaultContext())->first();
        $status = $recipientEntry->getStatus();

        static::assertIsString($status);
        static::assertEquals('direct', $status);
        $this->validateRecipientData($recipientEntry);
    }

    public function testRegisterNewsletterForCustomerDoi(): void
    {
        $this->systemConfig->set('core.newsletter.doubleOptIn', true);

        $browser = $this->login();
        $data = [
            'option' => 'subscribe',
        ];

        $browser->request(
            'POST',
            '/widgets/account/newsletter',
            $this->tokenize('frontend.account.newsletter', $data)
        );

        $response = $browser->getResponse();
        $content = $response->getContent();

        static::assertIsString($content);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());

        $repo = $this->getContainer()->get('newsletter_recipient.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, Context::createDefaultContext())->first();

        $browser->request(
            'GET',
            '/newsletter-subscribe?em=' . hash('sha1', 'nltest@example.com') . '&hash=' . $recipientEntry->getHash()
        );

        $response = $browser->getResponse();
        $content = $response->getContent();

        static::assertIsString($content);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, Context::createDefaultContext())->first();
        $status = $recipientEntry->getStatus();

        static::assertIsString($status);
        static::assertEquals('optIn', $status);
        $this->validateRecipientData($recipientEntry);
    }

    private function login(): KernelBrowser
    {
        $customer = $this->createCustomer();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $customer->getEmail(),
                'password' => 'test',
            ])
        );
        $response = $browser->getResponse();
        $content = $response->getContent();

        static::assertIsString($content);
        static::assertSame(200, $response->getStatusCode(), $content);

        $browser->request('GET', '/');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        $context = $response->getContext();

        static::assertNotNull($context);
        static::assertNotNull($context->getCustomer());

        return $browser;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerData = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'nltest@example.com',
            'password' => 'test',
            'title' => 'Dr.',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create([$this->customerData], Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }

    private function validateRecipientData(NewsletterRecipientEntity $recipientEntry): void
    {
        static::assertSame($this->customerData['email'], $recipientEntry->getEmail());
        static::assertSame($this->customerData['salutationId'], $recipientEntry->getSalutationId());
        static::assertSame($this->customerData['title'], $recipientEntry->getTitle());
        static::assertSame($this->customerData['firstName'], $recipientEntry->getFirstName());
        static::assertSame($this->customerData['lastName'], $recipientEntry->getLastName());
        static::assertSame($this->customerData['defaultShippingAddress']['zipcode'], $recipientEntry->getZipCode());
        static::assertSame($this->customerData['defaultShippingAddress']['city'], $recipientEntry->getCity());
        static::assertSame($this->customerData['defaultShippingAddress']['street'], $recipientEntry->getStreet());
    }
}
