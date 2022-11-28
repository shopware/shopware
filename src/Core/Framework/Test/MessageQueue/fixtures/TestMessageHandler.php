<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\fixtures;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

/**
 * @internal
 */
final class TestMessageHandler implements MessageSubscriberInterface
{
    public function __invoke(): void
    {
    }

    /**
     * @return iterable<class-string<AsyncMessageInterface>>
     */
    public static function getHandledMessages(): iterable
    {
        yield FooMessage::class;
        yield BarMessage::class;
    }
}
