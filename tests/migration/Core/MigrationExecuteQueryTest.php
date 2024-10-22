<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Test\NullConnection;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(MigrationCollection::class)]
class MigrationExecuteQueryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testExecuteQueryDoesNotPerformWriteOperations(): void
    {
        $nullConnection = new NullConnection();
        $nullConnection->setOriginalConnection($this->getContainer()->get(Connection::class));

        $loader = $this->getContainer()->get(MigrationCollectionLoader::class);
        $migrationCollection = $loader->collectAll();

        $exceptions = [];
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
                $exceptions[] = \sprintf('%s Trace: %s', NullConnection::EXCEPTION_MESSAGE, $e->getTraceAsString());
            }
            // ignore error because it is possible that older migrations just don't work on read anymore
        }
        static::assertEmpty($exceptions, print_r($exceptions, true));
    }
}
