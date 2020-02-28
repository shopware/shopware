<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\Generator;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiLoader;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class OpenApiLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const TESTFS = 'testfs';

    /**
     * @before
     */
    public function registerTestFileStreamWrapper(): void
    {
        stream_wrapper_register(self::TESTFS, FlysystemReadOnlyStreamWrapper::class);
    }

    /**
     * @after
     */
    public function unregisterTestFileStreamWrapper(): void
    {
        stream_wrapper_unregister(self::TESTFS);
    }

    /**
     * @OA\Get(
     *      path="/_testDummyDeclarationFromPluginVendorDir",
     *      description="Dummy API declaration for testing purposes",
     *      operationId="testDummy",
     *      tags={},
     *      @OA\Response(
     *          response="400",
     *          ref="#/components/responses/400"
     *      )
     * )
     */
    public function testOpenApiLoaderExcludesFilesInPluginVendorDirectories(): void
    {
        $sourceFileWithApiDeclarations = (new \ReflectionClass(ApiController::class))->getFileName();

        $pluginFile = '/custom/plugins/TestPlugin/Core/Controller/ApiController.php';
        $pluginVendorFile = '/custom/plugins/TestPlugin/vendor/some/library/src/Core/ApiController.php';

        $fileSystem = $this->getFilesystem('shopware.filesystem.private');
        $this->createOpenApiLoaderSearchPathDirectories($fileSystem);
        $fileSystem->put($pluginFile, file_get_contents($sourceFileWithApiDeclarations));
        $fileSystem->put($pluginVendorFile, file_get_contents(__FILE__));

        $openApi = (new OpenApiLoader(self::TESTFS . '://'))->load(false);
        $apiDeclarations = json_decode($openApi->toJson(), true);

        $message1 = 'Expected API declaration from file in plugin folder was not loaded.';
        static::assertArrayHasKey('/_search', $apiDeclarations['paths'], $message1);

        $message2 = 'API declaration from file in plugin vendor folder was not expected to be loaded.';
        static::assertArrayNotHasKey('/_testDummyDeclarationFromPluginVendorDir', $apiDeclarations['paths'], $message2);
    }

    private function createOpenApiLoaderSearchPathDirectories(Filesystem $fileSystem): void
    {
        $fileSystem->createDir('/src');
        $fileSystem->createDir('/vendor/shopware');
        $fileSystem->createDir('/custom/plugins');
    }
}
