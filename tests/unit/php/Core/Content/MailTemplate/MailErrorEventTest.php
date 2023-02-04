<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Framework\Context;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent
 *
 * @package system-settings
 */
class MailErrorEventTest extends TestCase
{
    public function testInstantiate(): void
    {
        $exception = new \Exception('exception');
        $context = Context::createDefaultContext();

        $event = new MailErrorEvent(
            $context,
            Logger::ERROR,
            $exception,
            'Test',
            '{{ subject }}',
            [
                'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
                'shopName' => 'Storefront',
            ],
        );

        static::assertSame('Test', $event->getMessage());
        static::assertSame(400, $event->getLogLevel());
        static::assertSame([
            'exception' => (string) $exception,
            'message' => 'Test',
            'template' => '{{ subject }}',
            'eventName' => 'checkout.order.placed',
            'templateData' => [
                'eventName' => 'checkout.order.placed',
                'shopName' => 'Storefront',
            ],
        ], $event->getLogData());
        static::assertSame('mail.sent.error', $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($exception, $event->getThrowable());
    }
}
