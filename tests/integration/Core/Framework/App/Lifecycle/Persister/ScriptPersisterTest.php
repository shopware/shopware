<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\Store\ServiceBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ScriptPersisterTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;
    use ServiceBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testRefreshDoesNotDeleteExistingScripts(): void
    {
        $appPath = __DIR__ . '/../../Manifest/_fixtures/test';
        $this->installApp($appPath);

        static::assertSame(6, $this->fetchAppScriptCount());

        $scriptPersister = static::getContainer()->get(ScriptPersister::class);

        $scriptPersister->refresh();

        static::assertSame(6, $this->fetchAppScriptCount());
        $this->removeApp($appPath);
    }

    public function testRefreshDoesNotLoadServiceScripts(): void
    {
        $appPath = __DIR__ . '/../../Manifest/_fixtures/test';
        $this->installService($appPath);

        static::assertSame(6, $this->fetchAppScriptCount());

        // delete scripts so we can check refresh does nothing.
        // Initial install of service and update should update scripts still.
        $this->deleteScripts();

        $scriptPersister = static::getContainer()->get(ScriptPersister::class);

        $scriptPersister->refresh();

        static::assertSame(0, $this->fetchAppScriptCount());
        $this->removeApp($appPath);
    }

    private function deleteScripts(): void
    {
        $this->connection->fetchOne('DELETE FROM script');
    }

    private function fetchAppScriptCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(id) FROM script');
    }
}
