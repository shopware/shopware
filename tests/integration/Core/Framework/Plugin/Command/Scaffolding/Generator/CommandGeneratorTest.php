<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\CommandGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class CommandGeneratorTest extends TestCase
{
    private Filesystem $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/' . uniqid('command-generator-', true);
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testGeneratedCommandIsInvokable(): void
    {
        $configuration = new PluginScaffoldConfiguration(
            'TestPlugin',
            'MyNamespace',
            '/path/to/directory',
            [CommandGenerator::OPTION_NAME => true],
        );
        $stubs = new StubCollection();
        (new CommandGenerator())->generateStubs($configuration, $stubs);

        $content = $stubs->get('src/Command/ExampleCommand.php')?->getContent();
        static::assertNotNull($content);

        $path = $this->directory . '/ExampleCommand.php';
        $this->filesystem->dumpFile($path, $content);
        require $path;

        $class = $configuration->namespace . '\\Command\\ExampleCommand';
        static::assertTrue(class_exists($class));
        $command = new $class();
        static::assertInstanceOf(Command::class, $command);
        static::assertIsCallable($command);

        $tester = new CommandTester($command);
        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertSame('It works!' . \PHP_EOL, $tester->getDisplay());
    }
}
