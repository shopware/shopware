<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MigrationExecuteQueryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testExecuteQueryDoesNotPerformWriteOperations(): void
    {
        $nullConnection = new NullConnection();
        $nullConnection->setOriginalConnection($this->getContainer()->get(Connection::class));

        $migrationCollection = $this->getContainer()->get(MigrationCollectionLoader::class)->collectAll();

        try {
            foreach ($migrationCollection as $migrations) {
                /** @var class-string<MigrationStep> $migrationClass */
                foreach ($migrations->getMigrationSteps() as $migrationClass) {
                    $migration = new $migrationClass();
                    $migration->update($nullConnection);
                    $migration->updateDestructive($nullConnection);
                }
            }
        } catch (\Exception $e) {
            if ($e->getMessage() === NullConnection::EXCEPTION_MESSAGE) {
                static::fail(sprintf('%s Trace: %s', NullConnection::EXCEPTION_MESSAGE, $e->getTraceAsString()));
            }
            //ignore error because it is possible that older migrations just don't work on read anymore
        }
        static::assertTrue(true, 'Annotation @doesNotPerformAssertions is bad because the error is not exposed');
    }
}
