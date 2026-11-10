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
use Shopware\Core\DevOps\Test\Command\MakeCoverageTestCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Stub\Framework\BundleFixture;
use Shopware\Core\Test\Stub\Framework\Util\InMemoryUtilFilesystem;
use Shopware\Tests\Unit\Core\DevOps\System\Command\OpenApiValidationCommandTest;
use Shopware\Tests\Unit\Core\DevOps\Test\Command\Fixture\Migration1763996000Dummy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MakeCoverageTestCommand::class)]
class MakeCoverageTestCommandTest extends TestCase
{
    /**
     * Relative to the repository root, so the command's "/tests/unit" to "/tests/migration" replacement only hits the generated test path.
     */
    private const PROJECT_DIR = 'tests/unit/Core/DevOps/Test/Command/_fixtures/make-coverage/project';

    /**
     * Source files the command checks for existence by their path relative to the repository root.
     */
    private const SOURCE_FILES = [
        'src/Core/DevOps/DevOps.php',
        'src/Core/Framework/ShopwareException.php',
    ];

    private InMemoryUtilFilesystem $filesystem;

    protected function setUp(): void
    {
        $files = [];
        foreach (self::SOURCE_FILES as $sourceFile) {
            $files[$sourceFile] = (string) file_get_contents($sourceFile);
        }

        $this->filesystem = new InMemoryUtilFilesystem($files);
    }

    public function testExecuteInvalidClasses(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->never())->method('getBundle');

        $command = new MakeCoverageTestCommand(self::PROJECT_DIR, $this->filesystem, $kernel);

        $tester = new CommandTester($command);
        $tester->execute([
            'classes' => [
                'not-a-class', // not a class
                'src/Core/DevOps/NotAClass.php', // pass a string that is a php file not existing
            ],
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());

        static::assertSame([], $this->writtenFiles());
    }

    public function testExecute(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->never())->method('getBundle');

        $command = new MakeCoverageTestCommand(self::PROJECT_DIR, $this->filesystem, $kernel);

        $tester = new CommandTester($command);
        $tester->execute([
            'classes' => [
                'not-a-class', // not a class
                Migration1763996000Dummy::class, // migration test
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
        static::assertSame([
            self::PROJECT_DIR . '/tests/migration/Tests/Unit/Core/DevOps/Test/Command/Fixture/Migration1763996000DummyTest.php' => $this->getMigrationTestTemplate(),
            self::PROJECT_DIR . '/tests/unit/Core/DevOps/DevOpsTest.php' => $this->getDevOpsTestTemplate(),
        ], $this->writtenFiles());

        // execute again to test if the file is not rewrite
        $tester->execute([
            'classes' => [
                'not-a-class', // not a class
                Migration1763996000Dummy::class, // migration test
                'src/Core/DevOps/DevOps.php', // pass a string that is a php file that is a class
                'src/Core/Framework/ShopwareException.php', // pass a string that is a php file that is not a class
                'src/Core/DevOps/NotAClass.php', // pass a string that is a php file not existing
                ShopwareHttpException::class, // is not instantiable
                OpenApiValidationCommandTest::class, // code coverage ignore because its a test
                UnusedMediaSubscriber::class, // code coverage ignore
            ],
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('already exists', $tester->getDisplay());
        static::assertSame([
            self::PROJECT_DIR . '/tests/migration/Tests/Unit/Core/DevOps/Test/Command/Fixture/Migration1763996000DummyTest.php' => $this->getMigrationTestTemplate(),
            self::PROJECT_DIR . '/tests/unit/Core/DevOps/DevOpsTest.php' => $this->getDevOpsTestTemplate(),
        ], $this->writtenFiles());
    }

    /**
     * @return array<string, string>
     */
    private function writtenFiles(): array
    {
        return array_diff_key($this->filesystem->dumpedFiles(), array_flip(self::SOURCE_FILES));
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
#[Package('framework')]
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

namespace Shopware\Tests\Migration\Tests\Unit\Core\DevOps\Test\Command\Fixture;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Tests\Unit\Core\DevOps\Test\Command\Fixture\Migration1763996000Dummy;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1763996000Dummy::class)]
class Migration1763996000DummyTest extends TestCase
{
    private Connection \$connection;

    protected function setUp(): void
    {
        \$this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        \$migration = new Migration1763996000Dummy();
        static::assertSame(9999999, \$migration->getCreationTimestamp());

        // make sure a migration can run multiple times without failing
        \$migration->update(\$this->connection);
        \$migration->update(\$this->connection);
    }
}\n
EOF;
    }
}
