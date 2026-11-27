<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @internal
 */
#[Package('framework')]
final readonly class InvalidateMediaCache implements AsyncMessageInterface
{
    /**
     * @param list<string> $mediaIds
     */
    public function __construct(public array $mediaIds)
    {
    }

    /**
     * @param array<string> $mediaIds
     */
    public static function dispatch(MessageBusInterface $bus, array $mediaIds, Context $context): void
    {
        if ($mediaIds === []) {
            return;
        }

        // An empty sender list runs the handler inline for synchronous writes.
        $stamps = $context->hasState(EntityIndexerRegistry::USE_INDEXING_QUEUE) ? [] : [new TransportNamesStamp([])];
        $bus->dispatch(new self(array_values(array_unique($mediaIds))), $stamps);
    }
}
