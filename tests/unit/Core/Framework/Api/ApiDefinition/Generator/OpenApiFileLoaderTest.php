<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApiFileLoader;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiFileLoader::class)]
class OpenApiFileLoaderTest extends TestCase
{
    public function testMergingOfFiles(): void
    {
        $paths = [__DIR__ . '/_fixtures/Api/ApiDefinition/Generator/Schema/StoreApi'];
        $fsLoader = new OpenApiFileLoader($paths);

        $spec = $fsLoader->loadOpenapiSpecification();

        static::assertArrayHasKey('paths', $spec);
        static::assertArrayHasKey('components', $spec);
        static::assertArrayHasKey('/_action/order_delivery/{orderDeliveryId}/state/{transition}', $spec['paths']);
        static::assertArrayHasKey('schemas', $spec['components']);
        static::assertArrayHasKey('infoConfigResponse', $spec['components']['schemas']);
        static::assertArrayHasKey('JsonOverrideEntity', $spec['components']['schemas']);
        static::assertArrayHasKey('relationship', $spec['components']['schemas']);
    }

    public function testEmptyFileLoader(): void
    {
        $fsLoader = new OpenApiFileLoader([]);

        $spec = $fsLoader->loadOpenapiSpecification();

        static::assertSame(
            [
                'paths' => [],
                'components' => [],
                'tags' => [],
            ],
            $spec
        );
    }

    public function testSchemaOverrides(): void
    {
        $paths = [
            __DIR__ . '/_fixtures/Api/ApiDefinition/Generator/Schema/StoreApi',
            __DIR__ . '/_fixtures/BundleWithOverride/Resources/Schema/StoreApi',
        ];
        $fsLoader = new OpenApiFileLoader($paths);

        $spec = $fsLoader->loadOpenapiSpecification();

        static::assertSame('Override', $spec['paths']['/_action/order_delivery/{orderDeliveryId}/state/{transition}']['post']['description']);
    }

    public function testMergingFilesUsesDeterministicNameOrder(): void
    {
        $filesystem = new Filesystem();
        $path = sys_get_temp_dir() . '/' . uniqid('openapi-file-loader-', true);
        $filesystem->mkdir($path);

        try {
            $filesystem->dumpFile($path . '/z-override.json', json_encode([
                'paths' => [
                    '/deterministic' => [
                        'get' => [
                            'description' => 'Sorted last',
                        ],
                    ],
                ],
            ], \JSON_THROW_ON_ERROR));
            $filesystem->dumpFile($path . '/a-base.json', json_encode([
                'paths' => [
                    '/deterministic' => [
                        'get' => [
                            'description' => 'Sorted first',
                        ],
                    ],
                ],
            ], \JSON_THROW_ON_ERROR));

            $fsLoader = new OpenApiFileLoader([$path]);

            $spec = $fsLoader->loadOpenapiSpecification();

            static::assertSame('Sorted last', $spec['paths']['/deterministic']['get']['description']);
        } finally {
            $filesystem->remove($path);
        }
    }
}
