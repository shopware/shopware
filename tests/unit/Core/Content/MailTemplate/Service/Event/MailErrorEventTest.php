<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(MailErrorEvent::class)]
class MailErrorEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new MailErrorEvent(
            Context::createDefaultContext()
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('name', $flow->data());
        static::assertEquals('mail.sent.error', $flow->getData('name'));
    }

    public function testInstantiate(): void
    {
        $exception = new \Exception('exception');
        $context = Context::createDefaultContext();

        $event = new MailErrorEvent(
            $context,
            Level::Error,
            $exception,
            'Test',
            '{{ subject }}',
            [
                'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
                'shopName' => 'Storefront',
            ],
        );

        static::assertSame('Test', $event->getMessage());
        static::assertSame(Level::Error, $event->getLogLevel());
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
