<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\FlowEvents;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @package business-ops
 *
 * @internal not intended for decoration or replacement
 */
class CachedFlowLoader extends AbstractFlowLoader implements EventSubscriberInterface, ResetInterface
{
    public const KEY = 'flow-loader';

    private array $flows = [];

    private AbstractFlowLoader $decorated;

    private CacheInterface $cache;

    public function __construct(
        AbstractFlowLoader $decorated,
        CacheInterface $cache
    ) {
        $this->decorated = $decorated;
        $this->cache = $cache;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        return [
            FlowEvents::FLOW_WRITTEN_EVENT => 'invalidate',
        ];
    }

    public function getDecorated(): AbstractFlowLoader
    {
        return $this->decorated;
    }

    public function load(): array
    {
        if (!empty($this->flows)) {
            return $this->flows;
        }

        $value = $this->cache->get(self::KEY, function (ItemInterface $item) {
            $item->tag([self::KEY]);

            return CacheValueCompressor::compress($this->getDecorated()->load());
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
