<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterRegisterEvent::class)]
class NewsletterRegisterEventTest extends TestCase
{
    public function testMailStructAddressesTheRecipientAndIsCached(): void
    {
        $recipient = new NewsletterRecipientEntity();
        $recipient->setEmail('jane@example.com');
        $recipient->setFirstName('Jane');
        $recipient->setLastName('Doe');

        $event = new NewsletterRegisterEvent(Context::createDefaultContext(), $recipient, 'https://shop.example/confirm', 'sales-channel-id');

        static::assertSame(['jane@example.com' => 'Jane Doe'], $event->getMailStruct()->getRecipients());

        // the struct is a snapshot taken on first access: later recipient changes must not leak into it
        $recipient->setFirstName('Changed');

        static::assertSame(['jane@example.com' => 'Jane Doe'], $event->getMailStruct()->getRecipients());
    }

    public function testExposesItsPayload(): void
    {
        $recipient = new NewsletterRecipientEntity();
        $recipient->setId('recipient-id');
        $context = Context::createDefaultContext();

        $event = new NewsletterRegisterEvent($context, $recipient, 'https://shop.example/confirm', 'sales-channel-id');

        static::assertSame(NewsletterRegisterEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($recipient, $event->getNewsletterRecipient());
        static::assertSame('recipient-id', $event->getNewsletterRecipientId());
        static::assertSame('https://shop.example/confirm', $event->getUrl());
        static::assertSame('sales-channel-id', $event->getSalesChannelId());
        static::assertSame(['url' => 'https://shop.example/confirm'], $event->getValues());
    }

    public function testAvailableDataDescribesTheEventPayload(): void
    {
        $data = NewsletterRegisterEvent::getAvailableData()->toArray();

        static::assertArrayHasKey('newsletterRecipient', $data);
        static::assertArrayHasKey('url', $data);
    }
}
