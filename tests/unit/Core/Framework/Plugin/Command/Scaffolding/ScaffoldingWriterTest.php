<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScaffoldingWriter::class)]
class ScaffoldingWriterTest extends TestCase
{
    public function testCanWriteStubs(): void
    {
        $filesystem = $this->createMock(Filesystem::class);

        $scaffoldingWriter = new ScaffoldingWriter($filesystem);

        $stubWithEmptyContent = static::createStub(Stub::class);
        $stubWithEmptyContent->method('getPath')->willReturn('src/Empty.php');
        $stubWithEmptyContent->method('getContent')->willReturn(null);

        $stubCollection = new StubCollection([
            Stub::raw('composer.json', 'Composer content'),
            Stub::raw('phpunit.xml', 'Phpunit content'),
            $stubWithEmptyContent,
        ]);

        $configuration = new PluginScaffoldConfiguration(
            'TestPlugin',
            'Test',
            'custom/plugins/TestPlugin'
        );

        $filesystem->expects($this->exactly(2))
            ->method('dumpFile')
            ->willReturnCallback(static function (string $filename, string $content): void {
                static::assertContains($filename, ['custom/plugins/TestPlugin/composer.json', 'custom/plugins/TestPlugin/phpunit.xml']);
                static::assertContains($content, ['Composer content', 'Phpunit content']);
            });

        $scaffoldingWriter->write($stubCollection, $configuration);
    }

    public function testDoesNotOverwriteExistingFilesAndAppendsAggregateFiles(): void
    {
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/shopware-scaffolding-' . uniqid('', true);
        $servicesFile = $directory . '/src/Resources/config/services.php';
        $composerFile = $directory . '/composer.json';

        try {
            $filesystem->dumpFile($servicesFile, "<?php\n\nreturn static function (): void {\n    existing();\n};\n");
            $filesystem->dumpFile($composerFile, '{"version":"custom"}');

            (new ScaffoldingWriter($filesystem))->write(
                new StubCollection([
                    Stub::append('src/Resources/config/services.php', "\n    generated();\n"),
                    Stub::raw('composer.json', '{"version":"generated"}'),
                ]),
                new PluginScaffoldConfiguration('TestPlugin', 'Test', $directory),
            );

            static::assertStringContainsString('existing();', (string) file_get_contents($servicesFile));
            static::assertStringContainsString('generated();', (string) file_get_contents($servicesFile));
            static::assertSame('{"version":"custom"}', file_get_contents($composerFile));
        } finally {
            $filesystem->remove($directory);
        }
    }

    public function testAppendsToMissingAndUnterminatedAggregateFiles(): void
    {
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/shopware-scaffolding-' . uniqid('', true);
        $existingFile = $directory . '/src/Resources/config/services.php';

        try {
            $filesystem->dumpFile($existingFile, "<?php\nexisting();\n");

            (new ScaffoldingWriter($filesystem))->write(
                new StubCollection([
                    Stub::append('src/Resources/config/services.php', "\ngenerated();\n"),
                    Stub::append('src/Resources/config/routes.php', "generatedRoute();\n"),
                ]),
                new PluginScaffoldConfiguration('TestPlugin', 'Test', $directory),
            );

            $servicesContent = (string) file_get_contents($existingFile);
            static::assertStringContainsString('existing();', $servicesContent);
            static::assertStringContainsString('generated();', $servicesContent);
            static::assertSame("generatedRoute();\n", file_get_contents($directory . '/src/Resources/config/routes.php'));
        } finally {
            $filesystem->remove($directory);
        }
    }

    public function testDoesNotAppendDuplicateAggregateContent(): void
    {
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/shopware-scaffolding-' . uniqid('', true);
        $servicesFile = $directory . '/src/Resources/config/services.php';
        $content = "<?php\nexisting();\ngenerated();\n";

        try {
            $filesystem->dumpFile($servicesFile, $content);

            (new ScaffoldingWriter($filesystem))->write(
                new StubCollection([Stub::append('src/Resources/config/services.php', "generated();\n")]),
                new PluginScaffoldConfiguration('TestPlugin', 'Test', $directory),
            );

            static::assertSame($content, file_get_contents($servicesFile));
        } finally {
            $filesystem->remove($directory);
        }
    }
}
