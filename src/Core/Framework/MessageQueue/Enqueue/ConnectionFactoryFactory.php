<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Enqueue;

use Doctrine\DBAL\Connection;
use Enqueue\ConnectionFactoryFactoryInterface;
use Interop\Queue\ConnectionFactory;

class ConnectionFactoryFactory implements ConnectionFactoryFactoryInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * If string is used, it should be a valid DSN.
     *
     * If array is used, it must have a dsn key with valid DSN string.
     * The other array options are treated as default values.
     * Options from DSN overwrite them.
     *
     * @param string|array $config
     *
     * @throws \InvalidArgumentException if invalid config provided
     */
    public function create($config): ConnectionFactory
    {
        $config = !is_array($config) ? [] : $config;
        $config = array_replace_recursive([
            'connection' => [],
            'table_name' => 'enqueue',
            'polling_interval' => 1000,
            'lazy' => true,
        ], $config);

        return new \Shopware\Core\Framework\MessageQueue\Enqueue\ConnectionFactory($this->connection, $config);
    }
}
