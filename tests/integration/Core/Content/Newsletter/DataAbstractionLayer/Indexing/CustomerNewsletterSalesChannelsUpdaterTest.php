<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Newsletter\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexer;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexingMessage;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerNewsletterSalesChannelsUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testUpdatesCustomerOnNewsletterSubscription(): void
    {
        $context = Context::createDefaultContext();
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($email);
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

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $customer = $customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($customer);

        static::assertNotNull($customer->getNewsletterSalesChannelIds());
        static::assertCount(1, $customer->getNewsletterSalesChannelIds());
        static::assertContains(TestDefaults::SALES_CHANNEL, $customer->getNewsletterSalesChannelIds());

        // subscribe to alternative sales channel and assert that array contains ids of both sales channels
        $newsletterRecipientB = $this->createNewsletterRecipient($context, $email, $alternativeSalesChannel['id'], NewsletterSubscribeRoute::STATUS_DIRECT);

        $customer = $customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($customer);

        static::assertNotNull($customer->getNewsletterSalesChannelIds());
        static::assertCount(2, $customer->getNewsletterSalesChannelIds());
        static::assertContains(TestDefaults::SALES_CHANNEL, $customer->getNewsletterSalesChannelIds());
        static::assertContains($alternativeSalesChannel['id'], $customer->getNewsletterSalesChannelIds());

        // unsubscribe first newsletter
        $this->unsubscribeNewsletterRecipient($context, $newsletterRecipientA);

        $customer = $customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($customer);

        static::assertNotNull($customer->getNewsletterSalesChannelIds());
        static::assertCount(1, $customer->getNewsletterSalesChannelIds());
        static::assertContains($alternativeSalesChannel['id'], $customer->getNewsletterSalesChannelIds());

        // unsubscribe second newsletter
        $this->unsubscribeNewsletterRecipient($context, $newsletterRecipientB);

        $customer = $customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($customer);

        static::assertNull($customer->getNewsletterSalesChannelIds());
    }

    public function testUpdatesCustomerOnCreationWithExistingNewsletterSubscription(): void
    {
        $context = Context::createDefaultContext();
        $email = Uuid::randomHex() . '@example.com';
        $this->createNewsletterRecipient($context, $email, TestDefaults::SALES_CHANNEL);
        $customerId = $this->createCustomer($email);

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $customer = $customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($customer);

        static::assertNotNull($customer->getNewsletterSalesChannelIds());
        static::assertCount(1, $customer->getNewsletterSalesChannelIds());
        static::assertContains(TestDefaults::SALES_CHANNEL, $customer->getNewsletterSalesChannelIds());
    }

    public function testDeleteNewsletterRecipientUpdatesCustomer(): void
    {
        $context = Context::createDefaultContext();
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($email);
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

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $customer = $customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($customer);

        static::assertNotNull($customer->getNewsletterSalesChannelIds());
        static::assertCount(2, $customer->getNewsletterSalesChannelIds());
        static::assertContains(TestDefaults::SALES_CHANNEL, $customer->getNewsletterSalesChannelIds());
        static::assertContains($alternativeSalesChannel['id'], $customer->getNewsletterSalesChannelIds());

        // delete first newsletter recipient
        $this->deleteNewsletterRecipient($context, $newsletterRecipientA);

        $customer = $customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($customer);

        static::assertNotNull($customer->getNewsletterSalesChannelIds());
        static::assertCount(1, $customer->getNewsletterSalesChannelIds());
        static::assertContains($alternativeSalesChannel['id'], $customer->getNewsletterSalesChannelIds());

        // delete second newsletter recipient
        $this->deleteNewsletterRecipient($context, $newsletterRecipientB);

        $customer = $customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($customer);

        static::assertNull($customer->getNewsletterSalesChannelIds());
    }

    #[DataProvider('createDataProvider')]
    public function testUpdateEmailNewsletterRecipientUpdateCustomer(\Closure $newsletterRecipientClosure, \Closure $criteriaClosure): void
    {
        $context = Context::createDefaultContext();

        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($email);

        $newsletterRecipientIds = $newsletterRecipientClosure($context, $email, $this);
        $criteria = empty($newsletterRecipientIds) ? $criteriaClosure(new Criteria(), $email) : $criteriaClosure(new Criteria(), $newsletterRecipientIds);

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $customer = $customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($customer);
        /** @var EntityRepository<NewsletterRecipientCollection> $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');
        $newsletterRecipients = $newsletterRecipientRepository->search($criteria, $context)->getEntities();

        static::assertCount($newsletterRecipients->count(), $newsletterRecipientIds);
        static::assertSame($customer->getEmail(), $email);

        foreach ($newsletterRecipients as $newsletterRecipient) {
            static::assertSame($newsletterRecipient->getEmail(), $email);
            static::assertSame($newsletterRecipient->getEmail(), $customer->getEmail());
        }

        $this->getContainer()->get('customer.repository')->upsert(
            [['id' => $customerId, 'email' => 'ytn@shopware.com']],
            $context
        );

        $customer = $customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($customer);
        $newsletterRecipients = $newsletterRecipientRepository->search($criteria, $context)->getEntities();

        static::assertCount($newsletterRecipients->count(), $newsletterRecipientIds);
        static::assertSame($customer->getEmail(), 'ytn@shopware.com');

        foreach ($newsletterRecipients as $newsletterRecipient) {
            static::assertSame($newsletterRecipient->getEmail(), 'ytn@shopware.com');
            static::assertSame($newsletterRecipient->getEmail(), $customer->getEmail());
        }
    }

    public static function createDataProvider(): \Generator
    {
        yield 'Email Newsletter Recipient Not Registered' => [
            fn (Context $context, string $email): array => [],
            fn (Criteria $criteria, string $email): Criteria => $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('email', $email),
                new EqualsFilter('email', 'ytn@shopware.com'),
            ])),
        ];

        yield 'Email Newsletter Recipient Registered' => [
            function (Context $context, string $email, self $me): array {
                $newsletterRecipientId = $me->createNewsletterRecipient($context, $email, TestDefaults::SALES_CHANNEL);

                return [
                    $newsletterRecipientId,
                ];
            },
            fn (Criteria $criteria, array $ids): Criteria => $criteria->setIds($ids),
        ];

        yield 'Email Newsletter Recipient Registered Multiple' => [
            function (Context $context, string $email, self $me): array {
                $salesChannel = $me->createSalesChannel();

                $newsletterRecipientId = $me->createNewsletterRecipient($context, $email, TestDefaults::SALES_CHANNEL);
                $newsletterRecipientId2 = $me->createNewsletterRecipient($context, $email, $salesChannel['id']);

                return [
                    $newsletterRecipientId,
                    $newsletterRecipientId2,
                ];
            },
            fn (Criteria $criteria, array $ids): Criteria => $criteria->setIds($ids),
        ];
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

        $messageBus = $this->getContainer()->get('messenger.bus.shopware');
        static::assertInstanceOf(TraceableMessageBus::class, $messageBus);

        foreach ($messageBus->getDispatchedMessages() as $message) {
            if (isset($message['message']) && $message['message'] instanceof NewsletterRecipientIndexingMessage) {
                $this->getContainer()->get(NewsletterRecipientIndexer::class)->handle($message['message']);
            }
        }
    }
}
