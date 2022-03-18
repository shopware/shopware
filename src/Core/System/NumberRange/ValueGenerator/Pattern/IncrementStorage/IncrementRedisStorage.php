<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\Lock\LockFactory;

class IncrementRedisStorage extends AbstractIncrementStorage
{
    /**
     * @var \Redis|\RedisCluster
     */
    private $redis;

    private LockFactory $lockFactory;

    /**
     * @param \Redis|\RedisCluster $redis
     */
    public function __construct($redis, LockFactory $lockFactory)
    {
        $this->redis = $redis;
        $this->lockFactory = $lockFactory;
    }

    /**
     * @inerhitDoc
     * This implementation focuses on getting the next increment value in a fast, non-blocking, atomic way
     * However some tradeoffs have to be made in the case that
     * the start value of the pattern is changed and simultaneous requests to reserve the next increment are made
     * this implementation is ensured to not block requests and will not produce the value twice, but at the tradeoff
     * that the continuity of the number ranges is not guaranteed in those edge cases
     */
    public function reserve(array $config): string
    {
        $key = $this->getKey($config);
        $increment = $this->redis->incr($key);

        // in the normal flow where the increment value is greater or equals the configured start value
        // we can use the stored increment value as is, thus we are atomic and don't need locking in the normal case
        if ($increment >= (int) $config['start']) {
            return (string) $increment;
        }

        // if the configured start value is greater than the current increment
        // we need a lock so that the value be only set once to the start value
        $lock = $this->lockFactory->createLock('number-range-' . $config['id']);

        if (!$lock->acquire()) {
            // we can't acquire the lock, meaning another request will increase the increment value to the new start value
            // so we can use the current increment for now
            return (string) $increment;
        }

        try {
            // to set the current increment to the new configured start we use incrementBy, rather than simply setting the new start value
            // to prevent issues where maybe the increment value is already increment to higher value by competing requests
            $newValue = $this->redis->incrBy($key, (int) $config['start'] - $increment);

            return (string) $newValue;
        } finally {
            $lock->release();
        }
    }

    public function preview(array $config): string
    {
        $lastNumber = $this->redis->get($this->getKey($config));

        if ($lastNumber === false || (int) $lastNumber < (int) $config['start']) {
            return (string) $config['start'];
        }

        return (string) ((int) $lastNumber + 1);
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        throw new DecorationPatternException(self::class);
    }

    private function getKey(array $configuration): string
    {
        return 'number_range:' . $configuration['id'];
    }
}
