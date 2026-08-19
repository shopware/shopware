<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterConfirmEvent::class)]
class NewsletterConfirmEventTest extends TestCase
{
    public function testMailStructAddressesTheRecipientByFullName(): void
    {
        $recipient = new NewsletterRecipientEntity();
        $recipient->setEmail('jane@example.com');
        $recipient->setFirstName('Jane');
        $recipient->setLastName('Doe');

        $event = new NewsletterConfirmEvent(Context::createDefaultContext(), $recipient, 'sales-channel-id');

        $mailStruct = $event->getMailStruct();

        static::assertSame(['jane@example.com' => 'Jane Doe'], $mailStruct->getRecipients());
        // the struct is built lazily on first access; the second call must return the
        // memoized instance instead of building a new, merely equal one
        static::assertSame($mailStruct, $event->getMailStruct());
    }
}
