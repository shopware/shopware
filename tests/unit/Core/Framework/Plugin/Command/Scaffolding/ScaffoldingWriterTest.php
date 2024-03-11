<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(ScaffoldingWriter::class)]
class ScaffoldingWriterTest extends TestCase
{
    public function testCanWriteStubs(): void
    {
        $filesystem = $this->createMock(Filesystem::class);

        $scaffoldingWriter = new ScaffoldingWriter($filesystem);

        $stubWithEmptyContent = $this->createMock(Stub::class);
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

        $filesystem->expects(static::exactly(2))
            ->method('dumpFile')
            ->willReturnCallback(function (string $filename, string $content): void {
                static::assertContains($filename, ['custom/plugins/TestPlugin/composer.json', 'custom/plugins/TestPlugin/phpunit.xml']);
                static::assertContains($content, ['Composer content', 'Phpunit content']);
            });

        $scaffoldingWriter->write($stubCollection, $configuration);
    }
}
