<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Storage;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @final
 */
#[Package('services-settings')]
class MySQLKeyValueStorage extends AbstractKeyValueStorage implements ResetInterface
{
    /**
     * @var array<int|string, mixed>|null
     */
    private ?array $config = null;

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function has(string $key): bool
    {
        $this->config = $this->config ?? $this->load();

        return \array_key_exists($key, $this->config);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->config = $this->config ?? $this->load();

        return $this->config[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->connection->executeStatement('REPLACE INTO `app_config` (`key`, `value`) VALUES (:key, :value)', [
            'key' => $key,
            'value' => \is_array($value) ? json_encode($value) : (string) $value,
        ]);

        $this->reset();
    }

    public function remove(string $key): void
    {
        $this->connection->delete('app_config', ['`key`' => $key]);

        if ($this->config && \array_key_exists($key, $this->config)) {
            unset($this->config[$key]);
        }
    }

    public function reset(): void
    {
        $this->config = null;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function load(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT `key`, `value` FROM `app_config`');
    }
}
