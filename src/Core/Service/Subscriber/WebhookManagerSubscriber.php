<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Webhook\Event\PreWebhooksDispatchEvent;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Service\ServiceSourceResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class WebhookManagerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreWebhooksDispatchEvent::class => 'filterDuplicates',
        ];
    }

    public function filterDuplicates(PreWebhooksDispatchEvent $event): void
    {
        [$webhooks, $serviceSystemUpdates] = $this->partitionArray($event->webhooks, function (Webhook $webhook) {
            return $webhook->eventName === UpdatePostFinishEvent::EVENT_NAME && $webhook->appSourceType === ServiceSourceResolver::name() ? 1 : 0;
        });

        $deduplicatedUpdates = [];
        foreach ($serviceSystemUpdates as $webhook) {
            $deduplicatedUpdates[$webhook->url . '-' . $webhook->onlyLiveVersion] = $webhook;
        }

        $event->webhooks = [...$webhooks, ...array_values($deduplicatedUpdates)];
    }

    /**
     * Partition an array with a callback, the callback should return an integer to determine the partition
     *
     * @template T
     *
     * @param list<T> $array
     * @param callable(T): int $callback
     *
     * @return array<int, list<T>>
     */
    private function partitionArray(array $array, callable $callback): array
    {
        return array_reduce(
            $array,
            function ($carry, $item) use ($callback) {
                $partition = $callback($item);

                if (!isset($carry[$partition])) {
                    $carry[$partition] = [];
                }

                $carry[$partition][] = $item;

                return $carry;
            },
            []
        );
    }
}
