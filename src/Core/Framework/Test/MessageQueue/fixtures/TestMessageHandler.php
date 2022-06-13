<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\fixtures;

use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

/**
 * @internal
 */
class TestMessageHandler implements MessageSubscriberInterface
{
    public function __invoke(): void
    {
    }

    public static function getHandledMessages(): iterable
    {
        yield FooMessage::class;
        yield BarMessage::class;
    }
}
