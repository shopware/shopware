<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class BlueGreenDeploymentService
{
    public const ENV_NAME = 'BLUE_GREEN_DEPLOYMENT';

    public function setEnvironmentVariable(Connection $connection): void
    {
        $_SERVER[self::ENV_NAME] = $_ENV[self::ENV_NAME] = $_SESSION[self::ENV_NAME] = $this->checkIfMayCreateTrigger($connection);
    }

    private function checkIfMayCreateTrigger(Connection $connection): bool
    {
        try {
            $connection->executeQuery($this->getCreateTableQuery());
            $connection->executeQuery($this->getTriggerQuery());
        } catch (Exception $exception) {
            return false;
        } finally {
            $connection->executeQuery('DROP TABLE IF EXISTS example');
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
