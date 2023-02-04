<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\Context;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent
 *
 * @package system-settings
 */
class MailSentTest extends TestCase
{
    public function testInstantiate(): void
    {
        $context = Context::createDefaultContext();

        $event = new MailSentEvent(
            'mail test',
            [
                'john.doe@example.com' => 'John doe',
                'jane.doe@example.com' => 'Jane doe',
            ],
            [
                'text/plain' => 'This is a plain text',
                'text/html' => 'This is a html text',
            ],
            $context,
            CheckoutOrderPlacedEvent::EVENT_NAME,
        );

        static::assertSame([
            'john.doe@example.com' => 'John doe',
            'jane.doe@example.com' => 'Jane doe',
        ], $event->getRecipients());
        static::assertSame(200, $event->getLogLevel());
        static::assertSame('mail test', $event->getSubject());
        static::assertSame([
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'subject' => 'mail test',
            'recipients' => [
                'john.doe@example.com' => 'John doe',
                'jane.doe@example.com' => 'Jane doe',
            ],
            'contents' => [
                'text/plain' => 'This is a plain text',
                'text/html' => 'This is a html text',
            ],
        ], $event->getLogData());
        static::assertSame('mail.sent', $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame([
            'text/plain' => 'This is a plain text',
            'text/html' => 'This is a html text',
        ], $event->getContents());
    }
}
