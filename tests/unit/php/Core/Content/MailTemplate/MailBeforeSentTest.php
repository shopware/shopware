<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent
 *
 * @package system-settings
 */
class MailBeforeSentTest extends TestCase
{
    public function testInstantiate(): void
    {
        $context = Context::createDefaultContext();
        $customerId = Uuid::randomHex();
        $email = (new Email())->subject('test subject')
            ->html('content html')
            ->text('content plain')
            ->to('test@shopware.com')
            ->from(new Address('test@shopware.com'));

        $event = new MailBeforeSentEvent(
            [
                'customerId' => $customerId,
            ],
            $email,
            $context,
            CheckoutOrderPlacedEvent::EVENT_NAME
        );

        static::assertSame(200, $event->getLogLevel());
        static::assertSame('mail.after.create.message', $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame([
            'customerId' => $customerId,
        ], $event->getData());
        static::assertSame([
            'data' => [
                'customerId' => $customerId,
            ],
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'message' => $email,
        ], $event->getLogData());
    }
}
