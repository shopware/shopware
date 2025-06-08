<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;

#[Package('checkout')]
class InitialStateIdLoader implements ResetInterface
{
    final public const CACHE_KEY = 'state-machine-initial-state-ids';

    private array $ids = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly CacheInterface $cache
    ) {
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
        return $this->cache->get(self::CACHE_KEY, fn () => $this->connection->fetchAllKeyValue(
            'SELECT technical_name, LOWER(HEX(`initial_state_id`)) as initial_state_id FROM state_machine'
        ));
    }
}
