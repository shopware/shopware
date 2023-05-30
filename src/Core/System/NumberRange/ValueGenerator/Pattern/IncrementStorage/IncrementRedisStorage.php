<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\Lock\LockFactory;

#[Package('checkout')]
class IncrementRedisStorage extends AbstractIncrementStorage
{
    /**
     * @param \Redis|\RedisCluster $redis
     */
    public function __construct(
        private $redis,
        private readonly LockFactory $lockFactory,
        private readonly EntityRepository $numberRangeRepository
    ) {
    }

    /**
     * @inheritDoc
     * This implementation focuses on getting the next increment value in a fast, non-blocking, atomic way
     * However some tradeoffs have to be made in the case that
     * the start value of the pattern is changed and simultaneous requests to reserve the next increment are made
     * this implementation is ensured to not block requests and will not produce the value twice, but at the tradeoff
     * that the continuity of the number ranges is not guaranteed in those edge cases
     */
    public function reserve(array $config): int
    {
        $key = $this->getKey($config['id']);
        $increment = $this->redis->incr($key);
        $start = $config['start'] ?? 1;

        // in the normal flow where the increment value is greater or equals the configured start value
        // we can use the stored increment value as is, thus we are atomic and don't need locking in the normal case
        if ($increment >= $start) {
            return $increment;
        }

        // if the configured start value is greater than the current increment
        // we need a lock so that the value be only set once to the start value
        $lock = $this->lockFactory->createLock('number-range-' . $config['id']);

        if (!$lock->acquire()) {
            // we can't acquire the lock, meaning another request will increase the increment value to the new start value
            // so we can use the current increment for now
            return $increment;
        }

        try {
            // to set the current increment to the new configured start we use incrementBy, rather than simply setting the new start value
            // to prevent issues where maybe the increment value is already increment to higher value by competing requests
            return $this->redis->incrBy($key, $start - $increment);
        } finally {
            $lock->release();
        }
    }

    /**
     * @inheritDoc
     */
    public function preview(array $config): int
    {
        $lastNumber = $this->redis->get($this->getKey($config['id']));
        $start = $config['start'] ?? 1;

        if (!$lastNumber || (int) $lastNumber < $start) {
            return $start;
        }

        return (int) $lastNumber + 1;
    }

    /**
     * @inheritDoc
     * We fetch all number range ids from the database and try to get the value stored for them in redis.
     * We don't use the `KEYS` command in redis to find all stored keys, because that would search the whole keyspace which can be huge
     */
    public function list(): array
    {
        $numberRangeIds = $this->getNumberRangeIds();
        $states = [];

        /** @var string $id */
        foreach ($numberRangeIds as $id) {
            $state = $this->redis->get($this->getKey($id));

            if (!$state) {
                continue;
            }

            $states[$id] = (int) $state;
        }

        return $states;
    }

    /**
     * @inheritDoc
     */
    public function set(string $configurationId, int $value): void
    {
        $this->redis->set($this->getKey($configurationId), $value);
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        throw new DecorationPatternException(self::class);
    }

    private function getKey(string $id): string
    {
        return 'number_range:' . $id;
    }

    private function getNumberRangeIds(): array
    {
        return $this->numberRangeRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();
    }
}
