<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Logging\Monolog;

use Doctrine\DBAL\Connection;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class DALHandler extends AbstractProcessingHandler
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $logEntryRepository;

    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(EntityRepositoryInterface $logEntryRepository, Connection $connection, int $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->logEntryRepository = $logEntryRepository;
        $this->connection = $connection;
        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
//        $payload = [
//            'message' => $record['message'],
//            'channel' => $record['channel'],
//            'level' => $record['level'],
//            'content' => $record['context'],
//            'extra'=> $record['extra']
//        ];

//        $this->logEntryRepository->create([$payload], Context::createDefaultContext());
        $envelope = [
            'id' => Uuid::randomBytes(),
            'message' => $record['message'],
            'level' => $record['level'],
            'channel' => $record['channel'],
            'content' => json_encode($record['context']),
            'extra' => json_encode($record['extra']),
            'updated_at' => null,
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ];

        $this->connection->insert('log_entry', $envelope);
    }
}
