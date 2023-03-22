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
 */
#[Package('business-ops')]
class CachedFlowLoader extends AbstractFlowLoader implements EventSubscriberInterface, ResetInterface
{
    final public const KEY = 'flow-loader';

    private array $flows = [];

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
        if (!empty($this->flows)) {
            return $this->flows;
        }

        $value = $this->cache->get(self::KEY, function (ItemInterface $item) {
            $item->tag([self::KEY]);

            return CacheValueCompressor::compress($this->decorated->load());
        });

        return $this->flows = CacheValueCompressor::uncompress($value);
    }

    public function invalidate(): void
    {
        $this->reset();
        $this->cache->delete(self::KEY);
    }

    public function reset(): void
    {
        $this->flows = [];
    }
}
