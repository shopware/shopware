<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Exception\CanNotDownloadPluginManagedByComposerException;
use Shopware\Core\Framework\Store\Exception\StoreNotAvailableException;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

class ExtensionDownloaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    /**
     * @var ExtensionDownloader
     */
    private $extensionDownloader;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);
        parent::setUp();
        $this->extensionDownloader = $this->getContainer()->get(ExtensionDownloader::class);
    }

    public function testDownloadExtension(): void
    {
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], '{"location": "http://localhost/my.zip"}'));
        $this->getRequestHandler()->append(new Response(200, [], file_get_contents(__DIR__ . '/../_fixtures/TestApp.zip')));

        $this->extensionDownloader->download('TestApp', Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));
        $expectedLocation = $this->getContainer()->getParameter('kernel.app_dir') . '/TestApp';

        static::assertFileExists($expectedLocation);
        (new Filesystem())->remove($expectedLocation);
    }

    public function testDownloadExtensionServerNotReachable(): void
    {
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], '{"location": "http://localhost/my.zip"}'));
        $this->getRequestHandler()->append(new Response(500, [], ''));

        static::expectException(StoreNotAvailableException::class);
        $this->extensionDownloader->download('TestApp', Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));
    }

    public function testDownloadWhichIsAnComposerExtension(): void
    {
        static::expectException(CanNotDownloadPluginManagedByComposerException::class);

        $this->getContainer()->get('plugin.repository')->create(
            [
                [
                    'name' => 'TestApp',
                    'label' => 'TestApp',
                    'baseClass' => 'TestApp',
                    'autoload' => [],
                    'version' => '1.0.0',
                    'managedByComposer' => true,
                ],
            ],
            Context::createDefaultContext()
        );

        $this->extensionDownloader->download('TestApp', Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));
    }
}
