<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Feature;
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
                PluginManagementService::PLUGIN => __DIR__ . '/_fixtures/plugins',
                PluginManagementService::APP => __DIR__ . '/_fixtures/apps',
            ],
            $this->filesystem
        );
    }

    public function testExtractPlugin(): void
    {
        $this->filesystem->copy(__DIR__ . '/_fixtures/archives/SwagFashionTheme.zip', __DIR__ . '/_fixtures/SwagFashionTheme.zip');

        $archive = __DIR__ . '/_fixtures/SwagFashionTheme.zip';

        $this->extractor->extract($archive, false, PluginManagementService::PLUGIN);

        static::assertFileExists(__DIR__ . '/_fixtures/plugins/SwagFashionTheme');
        static::assertFileExists(__DIR__ . '/_fixtures/plugins/SwagFashionTheme/SwagFashionTheme.php');

        $this->filesystem->remove(__DIR__ . '/_fixtures/plugins/SwagFashionTheme');
    }

    public function testExtractWithInvalidAppManifest(): void
    {
        $this->filesystem->copy(__DIR__ . '/_fixtures/archives/InvalidManifestShippingApp.zip', __DIR__ . '/_fixtures/TestShippingApp.zip');

        $archive = __DIR__ . '/_fixtures/TestShippingApp.zip';

        if (Feature::isActive('v6.7.0.0')) {
            $this->expectException(AppException::class);
        } else {
            $this->expectException(XmlParsingException::class);
        }

        $this->expectExceptionMessage('Unable to parse file "TestShippingApp/manifest.xml". Message: deliveryTime must not be empty');

        $this->extractor->extract($archive, false, PluginManagementService::APP);

        static::assertFileDoesNotExist(__DIR__ . '/_fixtures/apps/TestShippingApp');
    }
}
