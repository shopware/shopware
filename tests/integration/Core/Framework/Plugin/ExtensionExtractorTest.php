<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\ExtensionExtractor;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ExtensionExtractorTest extends TestCase
{
    use KernelTestBehaviour;

    protected ContainerInterface $container;

    private Filesystem $filesystem;

    private ExtensionExtractor $extractor;

    protected function setUp(): void
    {
        $this->container = static::getContainer();
        $this->filesystem = $this->container->get(Filesystem::class);
        $this->extractor = new ExtensionExtractor(
            __DIR__ . '/_fixtures/plugins',
            $this->filesystem
        );
    }

    public function testExtractPlugin(): void
    {
        $this->filesystem->copy(__DIR__ . '/_fixtures/archives/SwagFashionTheme.zip', __DIR__ . '/_fixtures/SwagFashionTheme.zip');

        $archive = __DIR__ . '/_fixtures/SwagFashionTheme.zip';

        $this->extractor->extract($archive, false);

        static::assertFileExists(__DIR__ . '/_fixtures/plugins/SwagFashionTheme');
        static::assertFileExists(__DIR__ . '/_fixtures/plugins/SwagFashionTheme/SwagFashionTheme.php');

        $this->filesystem->remove(__DIR__ . '/_fixtures/plugins/SwagFashionTheme');
    }

    public function testExtractWithPathTraversal(): void
    {
        $zipPath = __DIR__ . '/_fixtures/DirectoryTraversal.zip';

        $archive = new \ZipArchive();
        $archive->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $archive->addEmptyDir('MyPlugin');
        $archive->addFromString('MyPlugin/../../evil.php', 'This should not exist outside of the MyPlugin directory');
        $archive->close();

        $this->expectExceptionObject(PluginException::pluginExtractionError('Directory Traversal detected'));
        $this->extractor->extract($zipPath, false);
    }
}
