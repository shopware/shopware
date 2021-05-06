<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\DefaultSenderLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

class DefaultSenderLocatorTest extends TestCase
{
    public function testDefaultAsFallback(): void
    {
        $inner = $this->createMock(SendersLocatorInterface::class);
        $default = $this->createMock(SenderInterface::class);

        $defaultSenderLocator = new DefaultSenderLocator($inner, $default, 'default');

        $envelope = new Envelope(new \stdClass());
        $inner
            ->expects(static::once())
            ->method('getSenders')
            ->willReturnCallback(function ($actualEnvelope) use ($envelope) {
                static::assertSame($envelope, $actualEnvelope, 'envelope should be passed through');
                yield from [];
            });

        $actual = iterator_to_array($defaultSenderLocator->getSenders($envelope));
        static::assertSame(['default' => $default], $actual);
    }

    public function testPrefersSendersMatchingARule(): void
    {
        $inner = $this->createMock(SendersLocatorInterface::class);
        $default = $this->createMock(SenderInterface::class);

        $defaultSenderLocator = new DefaultSenderLocator($inner, $default, 'default');

        $otherSender = $this->createMock(SenderInterface::class);

        $envelope = new Envelope(new \stdClass());
        $inner
            ->expects(static::once())
            ->method('getSenders')
            ->willReturnCallback(function ($actualEnvelope) use ($otherSender, $envelope) {
                static::assertSame($envelope, $actualEnvelope, 'envelope should be passed through');
                yield from [
                    'not default' => $otherSender,
                ];
            });

        $actual = iterator_to_array($defaultSenderLocator->getSenders($envelope));
        static::assertSame(['not default' => $otherSender], $actual);
    }

    public function testDoesNotFailIfDefaultSenderIsNull(): void
    {
        $inner = $this->createMock(SendersLocatorInterface::class);

        $defaultSenderLocator = new DefaultSenderLocator($inner, null, 'default');

        $envelope = new Envelope(new \stdClass());
        $inner
            ->expects(static::once())
            ->method('getSenders')
            ->willReturnCallback(function ($actualEnvelope) use ($envelope) {
                static::assertSame($envelope, $actualEnvelope, 'envelope should be passed through');
                yield from [];
            });

        $actual = iterator_to_array($defaultSenderLocator->getSenders($envelope));
        static::assertEmpty($actual);
    }
}
