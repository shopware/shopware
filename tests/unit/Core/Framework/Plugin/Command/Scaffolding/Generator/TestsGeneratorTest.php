<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\TestsGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(TestsGenerator::class)]
class TestsGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn(false);

        $generator = new TestsGenerator($filesystem);

        static::assertFalse($generator->hasCommandOption());
        static::assertEmpty($generator->getCommandOptionName());
        static::assertEmpty($generator->getCommandOptionDescription());
    }

    public function testGenerateStubs(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn(false);

        $generator = new TestsGenerator($filesystem);

        $configuration = new PluginScaffoldConfiguration('TestPlugin', 'MyNamespace', '/path/to/directory');
        $stubCollection = new StubCollection();

        $generator->generateStubs($configuration, $stubCollection);

        static::assertCount(2, $stubCollection);

        static::assertTrue($stubCollection->has('phpunit.xml'));
        static::assertTrue($stubCollection->has('tests/TestBootstrap.php'));
    }
}
