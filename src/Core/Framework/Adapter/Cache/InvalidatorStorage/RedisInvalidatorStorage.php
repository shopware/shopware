<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class RedisInvalidatorStorage extends AbstractInvalidatorStorage
{
    private const KEY = 'invalidation';

    /**
     * @internal
     *
     * @param \Redis|\RedisCluster $redis
     */
    public function __construct(
        /** @phpstan-ignore shopware.propertyNativeType (Cannot type natively, as Symfony might change the implementation in the future) */
        private $redis,
        private readonly LoggerInterface $logger
    ) {
    }

    public function store(array $tags): void
    {
        $this->redis->sAdd(self::KEY, ...$tags);
    }

    public function loadAndDelete(): array
    {
        $tags = $this->loadAndDeleteMultiTransaction();

        if ($tags !== null) {
            return $tags;
        }

        return $this->loadAndDeleteSequentialFallback();
    }

    /**
     * @return list<string>|null
     */
    private function loadAndDeleteMultiTransaction(): ?array
    {
        try {
            /** @var array{0: list<string>, 1: mixed}|false $values */
            $values = $this
                ->redis
                ->multi()
                ->sMembers(self::KEY)
                ->del(self::KEY)
                ->exec();

            if ($values === false) {
                $this->logger->warning('Redis transaction failed (exec returned false), falling back to sequential execution.');

                return null;
            }

            return $values[0];
        } catch (\Throwable $e) {
            $this->logger->warning('Redis transaction failed, falling back to sequential execution. Error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function loadAndDeleteSequentialFallback(): array
    {
        // This breaks atomicity but ensures the queue is drained
        try {
            $tags = [];

            $chunk = $this->redis->sPop(self::KEY, 10000);
            while (\is_array($chunk) && !empty($chunk)) {
                foreach ($chunk as $tag) {
                    $tags[] = (string) $tag;
                }
                $chunk = $this->redis->sPop(self::KEY, 10000);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Sequential fallback: Could not load and delete tags from Redis. Error: ' . $e->getMessage());

            throw $e;
        }

        return $tags;
    }
}
