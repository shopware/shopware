<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailBeforeValidateEvent::class)]
class MailBeforeValidateEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new MailBeforeValidateEvent(
            ['foo' => 'bar'],
            Context::createDefaultContext(),
            ['template' => 'data'],
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('data', $flow->data());
        static::assertArrayHasKey('templateData', $flow->data());
        static::assertSame(['foo' => 'bar'], $flow->data()['data']);
        static::assertSame(['template' => 'data'], $flow->data()['templateData']);
    }

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

        static::assertSame(Level::Info, $event->getLogLevel());
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

    public function testDataAndTemplateDataCanBeAmended(): void
    {
        $event = new MailBeforeValidateEvent(['foo' => 'bar'], Context::createDefaultContext());

        $event->addData('baz', 'qux');
        $event->setTemplateData(['template' => 'data']);
        $event->addTemplateData('extra', 'value');

        static::assertSame(['foo' => 'bar', 'baz' => 'qux'], $event->getData());
        static::assertSame(['template' => 'data', 'extra' => 'value'], $event->getTemplateData());

        $event->setData(['replaced' => true]);

        static::assertSame(['replaced' => true], $event->getData());
    }

    public function testAvailableDataDescribesTheFlowPayload(): void
    {
        static::assertSame(['data', 'templateData'], array_keys(MailBeforeValidateEvent::getAvailableData()->toArray()));
    }
}
