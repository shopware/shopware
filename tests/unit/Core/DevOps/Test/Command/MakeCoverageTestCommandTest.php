<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Test\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Cms\Subscriber\UnusedMediaSubscriber;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\DevOps\System\Command\SystemDumpDatabaseCommand;
use Shopware\Core\DevOps\Test\Command\MakeCoverageTestCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Kernel;
use Shopware\Core\Migration\V6_5\Migration1670854818RemoveEventActionTable;
use Shopware\Storefront\Test\Controller\fixtures\BundleFixture;
use Shopware\Tests\Unit\Core\DevOps\System\Command\OpenApiValidationCommandTest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(MakeCoverageTestCommand::class)]
class MakeCoverageTestCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('shopware-sync-composer-version-test', true);
        $fs = new Filesystem();

        $fs->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->projectDir);
    }

    public function testExecuteInvalidClasses(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::never())->method('getBundle');

        $fileSystem = new Filesystem();
        $fileSystem->copy(__DIR__ . '/../../../../../../phpunit.xml.dist', $this->projectDir . '/phpunit.xml.dist');

        $command = new MakeCoverageTestCommand($this->projectDir, $fileSystem, $kernel);

        $tester = new CommandTester($command);
        $tester->execute([
            'classes' => [
                'not-a-class', // not a class
                'src/Core/DevOps/NotAClass.php', // pass a string that is a php file not existing
            ],
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());

        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/Test/Command/not-a-classTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/NotAClassTest.php'));
    }

    public function testExecute(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::never())->method('getBundle');

        $fileSystem = new Filesystem();

        $fileSystem->copy(__DIR__ . '/../../../../../../phpunit.xml.dist', $this->projectDir . '/phpunit.xml.dist');

        $command = new MakeCoverageTestCommand($this->projectDir, $fileSystem, $kernel);

        $tester = new CommandTester($command);
        $tester->execute([
            'classes' => [
                SystemDumpDatabaseCommand::class, // normal case
                'not-a-class', // not a class
                Migration1670854818RemoveEventActionTable::class, // migration test
                'src/Core/DevOps/DevOps.php', // pass a string that is a php file that is a class
                'src/Core/Framework/ShopwareException.php', // pass a string that is a php file that is not a class
                'src/Core/DevOps/NotAClass.php', // pass a string that is a php file not existing
                ShopwareHttpException::class, // is not instantiable
                OpenApiValidationCommandTest::class, // code coverage ignore because its a test
                UnusedMediaSubscriber::class, // code coverage ignore,
                ProductCollection::class, // code coverage ignore because its a collection, mentioned in phpunit.xml.dist
                ProductDefinition::class, // code coverage ignore because its a definition, mentioned in phpunit.xml.dist
                ProductEntity::class, // code coverage ignore because its an entity, mentioned in phpunit.xml.dist
                DocumentGenerateOperation::class, // code coverage ignore because its a struct, mentioned in phpunit.xml.dist
                StringField::class, // code coverage ignore because its a field, mentioned in phpunit.xml.dist
                CheckoutOrderPlacedEvent::class, // code coverage ignore because its a field, mentioned in phpunit.xml.dist
                'src/Core/Framework/Adapter/Twig/functions.php', // code coverage ignore because its a excluded file, mentioned in phpunit.xml.dist
                BundleFixture::class, // code coverage ignore because its in a excluded directory, mentioned in phpunit.xml.dist
            ],
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertTrue($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/System/Command/SystemDumpDatabaseCommandTest.php'));
        static::assertTrue($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/DevOpsTest.php'));
        static::assertTrue($fileSystem->exists($this->projectDir . '/tests/migration/Core/V6_5/Migration1670854818RemoveEventActionTableTest.php'));
        static::assertIsString($devOpsTest = file_get_contents($this->projectDir . '/tests/unit/Core/DevOps/DevOpsTest.php'));
        static::assertIsString($migrationTest = file_get_contents($this->projectDir . '/tests/migration/Core/V6_5/Migration1670854818RemoveEventActionTableTest.php'));
        static::assertEquals($this->getDevOpsTestTemplate(), $devOpsTest);
        static::assertEquals($this->getMigrationTestTemplate(), $migrationTest);

        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/Test/Command/not-a-classTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Content/Cms/Subscriber/UnusedMediaSubscriberTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Framework/ShopwareExceptionTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/NotAClassTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/System/Command/OpenApiValidationCommandTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Framework/ShopwareHttpExceptionTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Checkout/Cart/Event/CheckoutOrderPlacedEventTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Framework/Adapter/Twig/functionsTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Framework/Adapter/Twig/BundleFixtureTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Content/Product/ProductCollectionTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Content/Product/ProductDefinitionTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Content/Product/ProductEntityTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Checkout/Document/Struct/DocumentGenerateOperationTest.php'));

        // execute again to test if the file is not rewrite
        $tester->execute([
            'classes' => [
                SystemDumpDatabaseCommand::class, // normal case
                'not-a-class', // not a class
                Migration1670854818RemoveEventActionTable::class, // migration test
                'src/Core/DevOps/DevOps.php', // pass a string that is a php file that is a class
                'src/Core/Framework/ShopwareException.php', // pass a string that is a php file that is not a class
                'src/Core/DevOps/NotAClass.php', // pass a string that is a php file not existing
                ShopwareHttpException::class, // is not instantiable
                OpenApiValidationCommandTest::class, // code coverage ignore because its a test
                UnusedMediaSubscriber::class, // code coverage ignore
            ],
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());

        static::assertTrue($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/System/Command/SystemDumpDatabaseCommandTest.php'));
        static::assertTrue($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/DevOpsTest.php'));
        static::assertTrue($fileSystem->exists($this->projectDir . '/tests/migration/Core/V6_5/Migration1670854818RemoveEventActionTableTest.php'));

        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/Test/Command/not-a-classTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Content/Cms/Subscriber/UnusedMediaSubscriberTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Framework/ShopwareExceptionTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/NotAClassTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/DevOps/System/Command/OpenApiValidationCommandTest.php'));
        static::assertFalse($fileSystem->exists($this->projectDir . '/tests/unit/Core/Framework/ShopwareHttpExceptionTest.php'));
    }

    private function getDevOpsTestTemplate(): string
    {
        return <<<EOF
<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\DevOps\DevOps;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(DevOps::class)]
class DevOpsTest extends TestCase
{
    protected function setUp(): void
    {
        static::assertTrue(false);
    }
}\n
EOF;
    }

    private function getMigrationTestTemplate(): string
    {
        return <<<EOF
<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1670854818RemoveEventActionTable;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1670854818RemoveEventActionTable::class)]
class Migration1670854818RemoveEventActionTableTest extends TestCase
{
    private Connection \$connection;

    protected function setUp(): void
    {
        \$this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        \$migration = new Migration1670854818RemoveEventActionTable();
        static::assertSame(9999999, \$migration->getCreationTimestamp());

        // make sure a migration can run multiple times without failing
        \$migration->update(\$this->connection);
        \$migration->update(\$this->connection);
    }
}\n
EOF;
    }
}
