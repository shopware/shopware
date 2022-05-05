<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Loader;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;

class InitialStateIdLoader implements ResetInterface
{
    public const CACHE_KEY = 'state-machine-initial-state-ids';

    private Connection $connection;

    private CacheInterface $cache;

    private array $ids = [];

    /**
     * @internal
     */
    public function __construct(Connection $connection, CacheInterface $cache)
    {
        $this->connection = $connection;
        $this->cache = $cache;
    }

    public function reset(): void
    {
        $this->ids = [];
    }

    public function get(string $name): string
    {
        if (isset($this->ids[$name])) {
            return $this->ids[$name];
        }

        $this->ids = $this->load();

        return $this->ids[$name];
    }

    private function load(): array
    {
        return $this->cache->get(self::CACHE_KEY, function () {
            return $this->connection->fetchAllKeyValue(
                'SELECT technical_name, LOWER(HEX(`initial_state_id`)) as initial_state_id FROM state_machine'
            );
        });
    }
}
