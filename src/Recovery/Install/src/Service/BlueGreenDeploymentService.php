<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Service;

use Doctrine\DBAL\Connection;

class BlueGreenDeploymentService
{
    public const ENV_NAME = 'BLUE_GREEN_DEPLOYMENT';

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function setEnvironmentVariable(): void
    {
        $_ENV[self::ENV_NAME] = $_SESSION[self::ENV_NAME] = $this->checkIfMayCreateTrigger();
    }

    private function checkIfMayCreateTrigger(): bool
    {
        try {
            $this->connection->executeQuery($this->getCreateTableQuery());
            $this->connection->executeQuery($this->getTriggerQuery());
        } catch (\Doctrine\DBAL\DBALException $exception) {
            return false;
        } finally {
            $this->connection->executeQuery('DROP TABLE IF EXISTS example');
        }

        return true;
    }

    private function getCreateTableQuery(): string
    {
        return <<<'SQL'
            CREATE TABLE IF NOT EXISTS `example` (
              `id` int NOT NULL
            );
SQL;
    }

    private function getTriggerQuery(): string
    {
        return <<<'SQL'
            CREATE TRIGGER example_trigger BEFORE UPDATE ON `example`
                FOR EACH ROW
                BEGIN
                END;
SQL;
    }
}
