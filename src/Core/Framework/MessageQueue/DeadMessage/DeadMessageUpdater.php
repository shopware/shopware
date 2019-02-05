<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\DeadMessage;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class DeadMessageUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    public function __construct(Connection $connection, EntityCacheKeyGenerator $cacheKeyGenerator, TagAwareAdapterInterface $cache)
    {
        $this->connection = $connection;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
    }

    public function updateOriginalMessage(string $deadMessageId, object $originalMessage): void
    {
        $this->connection->update(DeadMessageDefinition::getEntityName(), [
            'serialized_original_message' => serialize($originalMessage),
        ], ['id' => Uuid::fromHexToBytes($deadMessageId)]);

        $cacheKeys = $this->cacheKeyGenerator->getEntityTag($deadMessageId, DeadMessageDefinition::class);
        $this->cache->invalidateTags([$cacheKeys]);
    }
}
