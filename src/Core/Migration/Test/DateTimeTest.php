<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('core')]
class DateTimeTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigrationDoesntUseDate(): void
    {
        $errorTemplate = <<<'EOF'
Attention: date(Defaults::(STORAGE_DATE_TIME_FORMAT|STORAGE_DATE_FORMAT)) has been used in "%s".
Please be aware that date doesn't support microseconds and is therefore incompatible with our default datetime format.
Please use (new \DateTime())->format(STORAGE_DATE_TIME_FORMAT) instead.
EOF;

        $classLoader = KernelLifecycleManager::getClassLoader();

        $migrationLoader = $this->getContainer()->get(MigrationCollectionLoader::class);
        foreach ($migrationLoader->collectAll() as $collection) {
            foreach (array_keys($collection->getMigrationSteps()) as $className) {
                /** @var string $file */
                $file = $classLoader->findFile($className);

                $result = preg_match_all(
                    '/date\(Defaults::(STORAGE_DATE_TIME_FORMAT|STORAGE_DATE_FORMAT).*\);/',
                    (string) file_get_contents($file),
                    $matches
                );

                static::assertSame(0, $result, sprintf($errorTemplate, basename($file)));
            }
        }
    }
}
