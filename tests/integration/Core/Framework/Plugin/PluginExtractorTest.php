<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\PluginExtractor;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class PluginExtractorTest extends TestCase
{
    use KernelTestBehaviour;

    protected ContainerInterface $container;

    /**
     * @var Filesystem
     */
    private $filesystem;

    private PluginExtractor $extractor;

    protected function setUp(): void
    {
        $this->container = $this->getContainer();
        $this->filesystem = $this->container->get(Filesystem::class);
        $this->extractor = new PluginExtractor(
            [
                PluginManagementService::PLUGIN => __DIR__ . '/_fixture/plugins',
                PluginManagementService::APP => __DIR__ . '/_fixture/apps',
            ],
            $this->filesystem
        );
    }

    public function testExtractPlugin(): void
    {
        $this->filesystem->copy(__DIR__ . '/_fixture/archives/SwagFashionTheme.zip', __DIR__ . '/_fixture/SwagFashionTheme.zip');

        $archive = __DIR__ . '/_fixture/SwagFashionTheme.zip';

        $this->extractor->extract($archive, false, PluginManagementService::PLUGIN);

        $extractedPlugin = $this->filesystem->exists(__DIR__ . '/_fixture/plugins/SwagFashionTheme');
        $extractedPluginBaseClass = $this->filesystem->exists(__DIR__ . '/_fixture/plugins/SwagFashionTheme/SwagFashionTheme.php');
        static::assertTrue($extractedPlugin);
        static::assertTrue($extractedPluginBaseClass);

        $this->filesystem->remove(__DIR__ . '/_fixture/plugins/SwagFashionTheme');
    }

    public function testExtractWithInvalidAppManifest(): void
    {
        $this->filesystem->copy(__DIR__ . '/_fixture/archives/InvalidManifestShippingApp.zip', __DIR__ . '/_fixture/TestShippingApp.zip');

        $archive = __DIR__ . '/_fixture/TestShippingApp.zip';

        $this->expectException(XmlParsingException::class);
        $this->expectExceptionMessage('Unable to parse file "TestShippingApp/manifest.xml". Message: deliveryTime must not be empty');

        $this->extractor->extract($archive, false, PluginManagementService::APP);

        static::assertFalse($this->filesystem->exists(__DIR__ . '/_fixture/apps/TestShippingApp'));
    }
}
