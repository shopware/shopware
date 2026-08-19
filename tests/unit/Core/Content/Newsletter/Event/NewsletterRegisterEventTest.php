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

    public function testAvailableDataDescribesTheEventPayload(): void
    {
        $data = NewsletterRegisterEvent::getAvailableData()->toArray();

        static::assertArrayHasKey('newsletterRecipient', $data);
        static::assertArrayHasKey('url', $data);
    }
}
