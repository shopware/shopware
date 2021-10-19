<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\FlowEvents;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal not intended for decoration or replacement
 */
class CachedFlowLoader extends AbstractFlowLoader implements EventSubscriberInterface
{
    public const KEY = 'flow-loader';

    private array $flows = [];

    private AbstractFlowLoader $decorated;

    private TagAwareAdapterInterface $cache;

    private LoggerInterface $logger;

    public function __construct(
        AbstractFlowLoader $decorated,
        TagAwareAdapterInterface $cache,
        LoggerInterface $logger
    ) {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->logger = $logger;
    }

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

        $item = $this->cache->getItem(self::KEY);

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . self::KEY);

                return $this->flows = CacheCompressor::uncompress($item);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . self::KEY);

        $flows = $this->getDecorated()->load();

        $item = CacheCompressor::compress($item, $flows);

        $item->tag([self::KEY]);

        $this->cache->save($item);

        return $this->flows = $flows;
    }

    public function invalidate(EntityWrittenEvent $event): void
    {
        $this->flows = [];
        $this->cache->deleteItem(self::KEY);
    }
}
