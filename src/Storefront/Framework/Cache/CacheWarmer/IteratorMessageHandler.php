<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

class IteratorMessageHandler extends AbstractMessageHandler
{
    /**
     * @var CacheRouteWarmerRegistry
     */
    private $registry;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    public function __construct(CacheRouteWarmerRegistry $registry, MessageBusInterface $bus)
    {
        $this->registry = $registry;
        $this->bus = $bus;
    }

    public function handle($message): void
    {
        if (!$message instanceof IteratorMessage) {
            return;
        }

        // each route cache warmer has an own message in queue (if send by CacheWarmerSender)
        $warmer = $this->registry->getWarmer($message->getWarmerClass());
        if (!$warmer) {
            return;
        }

        // fetch next offset of current warmer
        $next = $warmer->createMessage($message->getDomain(), $message->getOffset());

        // if no message returned - no more ids left
        if (!$next) {
            return;
        }
        // send "real" warm up message to queue
        $this->bus->dispatch($next);

        $this->bus->dispatch(
            new IteratorMessage($message->getDomain(), $message->getWarmerClass(), $next->getOffset())
        );
    }

    public static function getHandledMessages(): iterable
    {
        return [IteratorMessage::class];
    }
}
