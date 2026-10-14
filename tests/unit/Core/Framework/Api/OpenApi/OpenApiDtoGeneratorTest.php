<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerator;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiDtoGenerator::class)]
class OpenApiDtoGeneratorTest extends TestCase
{
    private Filesystem $filesystem;

    private string $projectDirectory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDirectory = sys_get_temp_dir() . '/open-api-dto-generator-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDirectory);
    }

    public function testSharedComponentsAreNotGeneratedInOperationNamespace(): void
    {
        $frameworkPath = $this->projectDirectory . '/src/Core/Framework';
        $schemaDirectory = $frameworkPath . '/Api/ApiDefinition/Generator/Schema/StoreApi';
        $this->filesystem->mkdir($schemaDirectory);
        $this->filesystem->copy(
            __DIR__ . '/_fixtures_generator/namespacedComponent.json',
            $schemaDirectory . '/schema.json',
        );

        $generator = new OpenApiDtoGenerator(
            new OpenApiDtoSchemaParser(),
            new OpenApiDtoClassRenderer(new MockClock('2026-09-17')),
            $this->filesystem,
            ['Framework' => ['path' => $frameworkPath]],
        );

        $generator->generate();

        static::assertFileExists($this->projectDirectory . '/src/Core/Example/Shared/SharedFailure.php');
        static::assertFileExists($this->projectDirectory . '/src/Core/Example/Shared/SharedError.php');
        static::assertFileExists($this->projectDirectory . '/src/Core/Example/Operation/ReadThingResponse.php');
        static::assertFileDoesNotExist($this->projectDirectory . '/src/Core/Example/Operation/SharedFailure.php');
        static::assertFileDoesNotExist($this->projectDirectory . '/src/Core/Example/Operation/SharedError.php');
    }
}
