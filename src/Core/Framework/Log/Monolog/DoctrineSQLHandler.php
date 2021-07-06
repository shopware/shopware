<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\Monolog;

use Doctrine\DBAL\Connection;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class DoctrineSQLHandler extends AbstractProcessingHandler
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param 100|200|250|300|400|500|550|600 $level
     */
    public function __construct(Connection $connection, int $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->connection = $connection;
        parent::__construct($level, $bubble);
    }

    protected function write(array $record): void
    {
        $envelope = [
            'id' => Uuid::randomBytes(),
            'message' => $record['message'],
            'level' => $record['level'],
            'channel' => $record['channel'],
            'context' => json_encode($record['context']),
            'extra' => json_encode($record['extra']),
            'updated_at' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        try {
            $this->connection->insert('log_entry', $envelope);
        } catch (\Throwable $e) {
            $envelope['context'] = json_encode([]);
            $envelope['extra'] = json_encode([]);
            $this->connection->insert('log_entry', $envelope);
        }
    }
}
