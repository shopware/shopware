<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Newsletter\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexer;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexingMessage;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

class CustomerNewsletterSalesChannelsUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testUpdatesCustomerOnNewsletterSubscription(): void
    {
        $context = Context::createDefaultContext();
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($context, $email);
        $alternativeSalesChannel = $this->createSalesChannel([
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/a',
            ]],
        ]);

        // create unrelated newsletter recipient which should not be involved when updating the customer's newsletter sales channel ids
        $this->createNewsletterRecipient($context, 'foobar@example.com', TestDefaults::SALES_CHANNEL);

        // subscribe to default sales channel and assert that array contains only that id
        $newsletterRecipientA = $this->createNewsletterRecipient($context, $email, TestDefaults::SALES_CHANNEL);

        /** @var CustomerEntity $customer */
        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), $context)->first();

        static::assertNotNull($customer->getNewsletterSalesChannelIds());
        static::assertCount(1, $customer->getNewsletterSalesChannelIds());
        static::assertContains(TestDefaults::SALES_CHANNEL, $customer->getNewsletterSalesChannelIds());

        // subscribe to alternative sales channel and assert that array contains ids of both sales channels
        $newsletterRecipientB = $this->createNewsletterRecipient($context, $email, $alternativeSalesChannel['id'], NewsletterSubscribeRoute::STATUS_DIRECT);

        /** @var CustomerEntity $customer */
        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), $context)->first();

        static::assertCount(2, $customer->getNewsletterSalesChannelIds());
        static::assertContains(TestDefaults::SALES_CHANNEL, $customer->getNewsletterSalesChannelIds());
        static::assertContains($alternativeSalesChannel['id'], $customer->getNewsletterSalesChannelIds());

        // unsubscribe first newsletter
        $this->unsubscribeNewsletterRecipient($context, $newsletterRecipientA);

        /** @var CustomerEntity $customer */
        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), $context)->first();

        static::assertNotNull($customer->getNewsletterSalesChannelIds());
        static::assertCount(1, $customer->getNewsletterSalesChannelIds());
        static::assertContains($alternativeSalesChannel['id'], $customer->getNewsletterSalesChannelIds());

        // unsubscribe second newsletter
        $this->unsubscribeNewsletterRecipient($context, $newsletterRecipientB);

        /** @var CustomerEntity $customer */
        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), $context)->first();

        static::assertNull($customer->getNewsletterSalesChannelIds());
    }

    public function testUpdatesCustomerOnCreationWithExistingNewsletterSubscription(): void
    {
        $context = Context::createDefaultContext();
        $email = Uuid::randomHex() . '@example.com';
        $this->createNewsletterRecipient($context, $email, TestDefaults::SALES_CHANNEL);
        $customerId = $this->createCustomer($context, $email);

        /** @var CustomerEntity $customer */
        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), $context)->first();

        static::assertNotNull($customer->getNewsletterSalesChannelIds());
        static::assertCount(1, $customer->getNewsletterSalesChannelIds());
        static::assertContains(TestDefaults::SALES_CHANNEL, $customer->getNewsletterSalesChannelIds());
    }

    public function testDeleteNewsletterRecipientUpdatesCustomer(): void
    {
        $context = Context::createDefaultContext();
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($context, $email);
        $alternativeSalesChannel = $this->createSalesChannel([
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/a',
            ]],
        ]);

        $newsletterRecipientA = $this->createNewsletterRecipient($context, $email, TestDefaults::SALES_CHANNEL);
        $newsletterRecipientB = $this->createNewsletterRecipient($context, $email, $alternativeSalesChannel['id'], NewsletterSubscribeRoute::STATUS_DIRECT);

        /** @var CustomerEntity $customer */
        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), $context)->first();

        static::assertCount(2, $customer->getNewsletterSalesChannelIds());
        static::assertContains(TestDefaults::SALES_CHANNEL, $customer->getNewsletterSalesChannelIds());
        static::assertContains($alternativeSalesChannel['id'], $customer->getNewsletterSalesChannelIds());

        // delete first newsletter recipient
        $this->deleteNewsletterRecipient($context, $newsletterRecipientA);

        /** @var CustomerEntity $customer */
        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), $context)->first();

        static::assertCount(1, $customer->getNewsletterSalesChannelIds());
        static::assertContains($alternativeSalesChannel['id'], $customer->getNewsletterSalesChannelIds());

        // delete second newsletter recipient
        $this->deleteNewsletterRecipient($context, $newsletterRecipientB);

        /** @var CustomerEntity $customer */
        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), $context)->first();

        static::assertNull($customer->getNewsletterSalesChannelIds());
    }

    private function createCustomer(Context $context, string $email): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '2000',
            'email' => $email,
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], $context);

        return $customerId;
    }

    private function createNewsletterRecipient(
        Context $context,
        string $email,
        string $salesChannelId,
        string $status = NewsletterSubscribeRoute::STATUS_OPT_IN
    ): string {
        $id = Uuid::randomHex();

        $newsletterRecipient = [
            'id' => $id,
            'email' => $email,
            'status' => $status,
            'hash' => Uuid::randomHex(),
            'salesChannelId' => $salesChannelId,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
        ];

        $this->getContainer()
            ->get('newsletter_recipient.repository')
            ->upsert([$newsletterRecipient], $context);

        return $id;
    }

    private function unsubscribeNewsletterRecipient(
        Context $context,
        string $id
    ): string {
        $newsletterRecipient = [
            'id' => $id,
            'status' => NewsletterSubscribeRoute::STATUS_OPT_OUT,
        ];

        $this->getContainer()
            ->get('newsletter_recipient.repository')
            ->upsert([$newsletterRecipient], $context);

        return $id;
    }

    private function deleteNewsletterRecipient(
        Context $context,
        string $id
    ): void {
        $newsletterRecipient = [
            'id' => $id,
        ];

        $this->getContainer()
            ->get('newsletter_recipient.repository')
            ->delete([$newsletterRecipient], $context);

        $messages = $this->getContainer()->get('messenger.bus.shopware')->getDispatchedMessages();

        foreach ($messages as $message) {
            if (isset($message['message']) && $message['message'] instanceof NewsletterRecipientIndexingMessage) {
                $this->getContainer()->get(NewsletterRecipientIndexer::class)->handle($message['message']);
            }
        }
    }
}
