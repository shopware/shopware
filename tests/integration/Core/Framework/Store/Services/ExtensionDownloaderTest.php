<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Services;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('checkout')]
class ExtensionDownloaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private ExtensionDownloader $extensionDownloader;

    protected function setUp(): void
    {
        $this->extensionDownloader = $this->getContainer()->get(ExtensionDownloader::class);

        @mkdir($this->getContainer()->getParameter('kernel.app_dir'), 0777, true);
    }

    public function testDownloadExtension(): void
    {
        $this->getStoreRequestHandler()->reset();
        $this->getStoreRequestHandler()->append(new Response(200, [], '{"location": "http://localhost/my.zip", "type": "app"}'));
        $this->getStoreRequestHandler()->append(new Response(200, [], (string) file_get_contents(__DIR__ . '/../_fixtures/TestApp.zip')));

        $context = $this->createAdminStoreContext();

        $this->extensionDownloader->download('TestApp', $context);
        $expectedLocation = $this->getContainer()->getParameter('kernel.app_dir') . '/TestApp';

        static::assertFileExists($expectedLocation);
        (new Filesystem())->remove($expectedLocation);
    }

    public function testDownloadExtensionServerNotReachable(): void
    {
        $this->getStoreRequestHandler()->reset();
        $this->getStoreRequestHandler()->append(new Response(200, [], '{"location": "http://localhost/my.zip"}'));
        $this->getStoreRequestHandler()->append(new Response(500, [], ''));

        $context = $this->createAdminStoreContext();

        static::expectException(PluginException::class);
        static::expectExceptionMessage('Store is not available');
        $this->extensionDownloader->download('TestApp', $context);
    }

    public function testDownloadWhichIsAnComposerExtension(): void
    {
        static::expectException(StoreException::class);

        $this->getContainer()->get('plugin.repository')->create(
            [
                [
                    'name' => 'TestApp',
                    'label' => 'TestApp',
                    'baseClass' => 'TestApp',
                    'path' => $this->getContainer()->getParameter('kernel.project_dir') . '/vendor/swag/TestApp',
                    'autoload' => [],
                    'version' => '1.0.0',
                    'managedByComposer' => true,
                ],
            ],
            Context::createDefaultContext()
        );

        $this->extensionDownloader->download('TestApp', Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));
    }

    public function testDownloadExtensionWhichIsALocalComposerPlugin(): void
    {
        $this->getStoreRequestHandler()->reset();
        $this->getStoreRequestHandler()->append(new Response(200, [], '{"location": "http://localhost/my.zip", "type": "app"}'));
        $this->getStoreRequestHandler()->append(new Response(200, [], (string) file_get_contents(__DIR__ . '/../_fixtures/TestApp.zip')));

        $pluginPath = $this->getContainer()->getParameter('kernel.plugin_dir') . '/TestApp';
        $projectPath = $this->getContainer()->getParameter('kernel.project_dir');

        $this->getContainer()->get('plugin.repository')->create(
            [
                [
                    'name' => 'TestApp',
                    'label' => 'TestApp',
                    'baseClass' => 'TestApp',
                    'path' => str_replace($projectPath . '/', '', $pluginPath),
                    'autoload' => [],
                    'version' => '1.0.0',
                    'managedByComposer' => true,
                ],
            ],
            Context::createDefaultContext()
        );

        $context = $this->createAdminStoreContext();

        $this->extensionDownloader->download('TestApp', $context);
        $expectedLocation = $this->getContainer()->getParameter('kernel.app_dir') . '/TestApp';

        static::assertFileExists($expectedLocation);
        (new Filesystem())->remove($expectedLocation);
    }
}
