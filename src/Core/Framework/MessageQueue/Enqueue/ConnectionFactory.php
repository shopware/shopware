<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Enqueue;

use Doctrine\DBAL\Connection;
use Enqueue\Dbal\DbalContext;
use Interop\Queue\Context;

class ConnectionFactory implements \Interop\Queue\ConnectionFactory
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array
     */
    private $config;

    public function __construct(Connection $connection, array $config)
    {
        $this->connection = $connection;
        $this->config = $config;
    }

    public function createContext(): Context
    {
        return new DbalContext($this->connection, $this->config);
    }
}
