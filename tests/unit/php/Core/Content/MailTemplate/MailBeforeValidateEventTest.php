<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent
 *
 * @package system-settings
 */
class MailBeforeValidateEventTest extends TestCase
{
    public function testInstantiate(): void
    {
        $context = Context::createDefaultContext();
        $customerId = Uuid::randomHex();

        $event = new MailBeforeValidateEvent(
            [
                'customerId' => $customerId,
            ],
            $context,
            [
                'user' => 'admin',
                'recoveryUrl' => 'http://some-url.com',
                'resetUrl' => 'http://some-url.com',
                'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            ]
        );

        static::assertSame(200, $event->getLogLevel());
        static::assertSame('mail.before.send', $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame([
            'customerId' => $customerId,
        ], $event->getData());
        static::assertSame([
            'user' => 'admin',
            'recoveryUrl' => 'http://some-url.com',
            'resetUrl' => 'http://some-url.com',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
        ], $event->getTemplateData());
        static::assertSame([
            'data' => [
                'customerId' => $customerId,
            ],
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'templateData' => [
                'user' => 'admin',
                'recoveryUrl' => 'http://some-url.com',
                'resetUrl' => 'http://some-url.com',
                'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            ],
        ], $event->getLogData());
    }
}
