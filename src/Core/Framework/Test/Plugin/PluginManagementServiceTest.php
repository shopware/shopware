<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\PluginExtractor;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Filesystem\Filesystem;

class PluginManagementServiceTest extends TestCase
{
    use KernelTestBehaviour;
    use PluginTestsHelper;

    /**
     * @var KernelPluginCollection
     */
    protected $container;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var PluginManagementService
     */
    private $managementService;

    protected function setUp(): void
    {
        $this->container = $this->getContainer();
        $this->filesystem = $this->container->get(Filesystem::class);

        $extractor = new PluginExtractor(__DIR__ . '/_fixture/plugins', $this->filesystem);
        $pluginService = $this->createPluginService(
            $this->getContainer()->get('plugin.repository'),
            $this->getContainer()->get('language.repository'),
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->getContainer()->get(PluginFinder::class)
        );

        $this->managementService = new PluginManagementService(__DIR__ . '/_fixture/plugins', new PluginZipDetector(), $extractor, $pluginService, $this->filesystem);

        $this->filesystem->copy(__DIR__ . '/_fixture/archives/SwagFashionTheme.zip', __DIR__ . '/_fixture/SwagFashionTheme.zip');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(__DIR__ . '/_fixture/plugins/SwagFashionTheme');
    }

    public function testExtractPluginZip(): void
    {
        $this->managementService->extractPluginZip(__DIR__ . '/_fixture/SwagFashionTheme.zip');

        $extractedPlugin = $this->filesystem->exists(__DIR__ . '/_fixture/plugins/SwagFashionTheme');
        $extractedPluginBaseClass = $this->filesystem->exists(__DIR__ . '/_fixture/plugins/SwagFashionTheme/SwagFashionTheme.php');
        static::assertTrue($extractedPlugin);
        static::assertTrue($extractedPluginBaseClass);
    }
}
