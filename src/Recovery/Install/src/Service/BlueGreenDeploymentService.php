<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 - Use `\Shopware\Core\Installer\Database\BlueGreenDeploymentService` instead
 */
class BlueGreenDeploymentService
{
    public const ENV_NAME = 'BLUE_GREEN_DEPLOYMENT';

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        Feature::triggerDeprecationOrThrow(
            '6.5.0',
            Feature::deprecatedClassMessage(__CLASS__, '6.5.0', \Shopware\Core\Installer\Database\BlueGreenDeploymentService::class)
        );

        $this->connection = $connection;
    }

    public function setEnvironmentVariable(): void
    {
        Feature::triggerDeprecationOrThrow(
            '6.5.0',
            Feature::deprecatedClassMessage(__CLASS__, '6.5.0', \Shopware\Core\Installer\Database\BlueGreenDeploymentService::class)
        );

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
