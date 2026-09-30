<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\FlowEvents;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal not intended for decoration or replacement
 *
 * @phpstan-import-type EventGroupedFlowHolders from AbstractFlowLoader
 */
#[Package('after-sales')]
class CachedFlowLoader extends AbstractFlowLoader implements EventSubscriberInterface, ResetInterface
{
    final public const KEY = 'flow-loader';

    /**
     * @var EventGroupedFlowHolders
     */
    private ?array $flows = null;

    public function __construct(
        private readonly AbstractFlowLoader $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FlowEvents::FLOW_WRITTEN_EVENT => 'invalidate',
        ];
    }

    public function load(): array
    {
        if ($this->flows !== null) {
            return $this->flows;
        }

        $fresh = null;

        $value = $this->cache->get(self::KEY, function (ItemInterface $item) use (&$fresh) {
            $item->tag([self::KEY]);

            $fresh = $this->decorated->load();

            return CacheValueCompressor::compress($fresh);
        });

        // the flows were loaded in this call, return them directly instead of
        // uncompressing the cache payload that was just compressed from them
        if ($fresh !== null) {
            return $this->flows = $fresh;
        }

        return $this->flows = CacheValueCompressor::uncompress($value);
    }

    public function invalidate(): void
    {
        $this->reset();
        $this->cache->delete(self::KEY);
    }

    public function reset(): void
    {
        $this->flows = null;
    }
}
