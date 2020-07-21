<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Newsletter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

class SalesChannelNewsletterControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->context = $context = Context::createDefaultContext();
        /** @var EntityRepositoryInterface $systemConfigRepository */
        $systemConfigRepository = $this->getContainer()->get('system_config.repository');

        /*
         * Add subscribeDomain because Headless SalesChannels don't have a domain
         */
        $this->getSalesChannelBrowser(); // must be called for initializing the SalesChannel
        $newsletterDomainConfig = [
            'id' => Uuid::randomHex(),
            'configurationKey' => 'core.newsletter.subscribeDomain',
            'configurationValue' => 'http://localhost',
            'salesChannelId' => $this->getSalesChannelApiSalesChannelId(),
        ];

        $systemConfigRepository->upsert([$newsletterDomainConfig], $context);
    }

    public function testSubscribe(): void
    {
        $email = Uuid::randomHex() . '@example.com';

        /** @var EntityRepositoryInterface $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/newsletter/subscribe', [
            'email' => $email,
        ]);
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals('notSet', $subscriptions->first()->getStatus());
    }

    public function testConfirm(): void
    {
        $email = Uuid::randomHex() . '@example.com';

        /** @var EntityRepositoryInterface $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/newsletter/subscribe', [
            'email' => $email,
        ]);
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        $hash = $subscriptions->first()->getHash();

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/newsletter/confirm', [
            'hash' => $hash,
        ]);

        $response = $this->getSalesChannelBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals('optIn', $subscriptions->first()->getStatus());
    }

    public function testUnsubscribeBeforeConfirm(): void
    {
        $email = Uuid::randomHex() . '@example.com';

        /** @var EntityRepositoryInterface $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/newsletter/subscribe', [
            'email' => $email,
        ]);
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/newsletter/unsubscribe', [
            'email' => $email,
        ]);

        $response = $this->getSalesChannelBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals('optOut', $subscriptions->first()->getStatus());
    }

    public function testUnsubscribeAfterConfirm(): void
    {
        $email = Uuid::randomHex() . '@example.com';

        /** @var EntityRepositoryInterface $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/newsletter/subscribe', [
            'email' => $email,
        ]);
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        $hash = $subscriptions->first()->getHash();

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/newsletter/confirm', [
            'hash' => $hash,
        ]);

        $response = $this->getSalesChannelBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals('optIn', $subscriptions->first()->getStatus());

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/newsletter/unsubscribe', [
            'email' => $email,
        ]);

        $response = $this->getSalesChannelBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals('optOut', $subscriptions->first()->getStatus());
    }

    public function testUpdate(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $firstName = Uuid::randomHex() . 'FirstName';
        /** @var EntityRepositoryInterface $newsletterRecipientRepository */
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/newsletter/subscribe', [
            'email' => $email,
        ]);
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals('notSet', $subscriptions->first()->getStatus());
        static::assertEmpty($subscriptions->first()->getFirstName());

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/newsletter/subscribe', [
            'id' => $subscriptions->first()->getId(),
            'firstName' => $firstName,
            'email' => $email,
        ]);

        $response = $this->getSalesChannelBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var newsletterRecipientCollection $subscriptions */
        $subscriptions = $newsletterRecipientRepository->search($criteria, $this->context);

        static::assertEquals($firstName, $subscriptions->first()->getFirstName());
    }
}
