<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterUnsubscribeEvent::class)]
class NewsletterUnsubscribeEventTest extends TestCase
{
    public function testMailStructAddressesTheRecipientByFullName(): void
    {
        $recipient = new NewsletterRecipientEntity();
        $recipient->setEmail('jane@example.com');
        $recipient->setFirstName('Jane');
        $recipient->setLastName('Doe');

        $event = new NewsletterUnsubscribeEvent(Context::createDefaultContext(), $recipient, 'sales-channel-id');

        $mailStruct = $event->getMailStruct();

        static::assertSame(['jane@example.com' => 'Jane Doe'], $mailStruct->getRecipients());

        // the struct is a snapshot taken on first access: later recipient
        // changes must not leak into it
        $recipient->setFirstName('Changed');

        static::assertSame(['jane@example.com' => 'Jane Doe'], $event->getMailStruct()->getRecipients());
    }
}
