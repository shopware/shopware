<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class NewsletterControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testRegisterNewsletterForCustomerDirect(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_14001', $this);
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

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());

        $repo = $this->getContainer()->get('newsletter_recipient.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals('direct', $recipientEntry->getStatus(), $recipientEntry->getStatus());
    }

    public function testRegisterNewsletterForCustomerDoi(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_14001', $this);
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

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

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

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals('optIn', $recipientEntry->getStatus(), $recipientEntry->getStatus());
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
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertNotNull($response->getContext()->getCustomer());

        return $browser;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
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
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'nltest@example.com',
                'password' => 'test',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }
}
