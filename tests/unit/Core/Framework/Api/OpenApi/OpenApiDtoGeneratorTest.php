<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGeneratedFile;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerationCheckResult;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerationResult;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerator;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoProperty;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(OpenApiDtoGenerator::class)]
#[CoversClass(OpenApiDtoSchemaParser::class)]
#[CoversClass(OpenApiDtoClassRenderer::class)]
#[CoversClass(OpenApiDtoDefinition::class)]
#[CoversClass(OpenApiDtoProperty::class)]
#[CoversClass(OpenApiDtoGeneratedFile::class)]
#[CoversClass(OpenApiDtoGenerationResult::class)]
#[CoversClass(OpenApiDtoGenerationCheckResult::class)]
class OpenApiDtoGeneratorTest extends TestCase
{
    public function testGenerateWritesGeneratedFiles(): void
    {
        $filesystem = new Filesystem();
        $projectRoot = $this->createProjectWithSchema($filesystem);
        $expectedFile = $this->expectedDtoPath($projectRoot);
        $generator = $this->createGenerator($projectRoot, $filesystem);

        try {
            $result = $generator->generate();

            static::assertSame([$expectedFile], $result->writtenFiles);
            static::assertFileExists($expectedFile);
            static::assertStringContainsString('final readonly class CheckResponse', (string) file_get_contents($expectedFile));
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    public function testCheckIgnoresLastGeneratedDate(): void
    {
        $filesystem = new Filesystem();
        $projectRoot = $this->createProjectWithSchema($filesystem);
        $expectedFile = $this->expectedDtoPath($projectRoot);
        $generator = $this->createGenerator($projectRoot, $filesystem, new \DateTimeImmutable('2026-07-07'));

        try {
            $generator->generate();
            $filesystem->dumpFile(
                $expectedFile,
                str_replace(
                    'Last generated: 2026-07-07',
                    'Last generated: 2020-01-01',
                    (string) file_get_contents($expectedFile),
                ),
            );

            $checkGenerator = $this->createGenerator($projectRoot, $filesystem, new \DateTimeImmutable('2026-07-08'));
            $result = $checkGenerator->check();

            static::assertSame([], $result->outdatedFiles);
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    public function testCheckReportsOutdatedGeneratedFile(): void
    {
        $filesystem = new Filesystem();
        $projectRoot = $this->createProjectWithSchema($filesystem);
        $expectedFile = $this->expectedDtoPath($projectRoot);
        $generator = $this->createGenerator($projectRoot, $filesystem);

        try {
            $generator->generate();
            $filesystem->dumpFile($expectedFile, '<?php declare(strict_types=1);');

            $result = $generator->check();

            static::assertSame([$expectedFile], $result->outdatedFiles);
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    private function createProjectWithSchema(Filesystem $filesystem): string
    {
        $projectRoot = sys_get_temp_dir() . '/open-api-dto-generator-' . bin2hex(random_bytes(4));
        $schemaDirectory = $projectRoot . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/components/schemas';
        $filesystem->mkdir($schemaDirectory);

        $schema = json_encode([
            'components' => [
                'schemas' => [
                    'CheckResponse' => [
                        OpenApiDtoGenerator::NAMESPACE_EXTENSION => 'Shopware\\Core\\Framework\\Api\\Dto',
                        'type' => 'object',
                        'properties' => [
                            'success' => [
                                'type' => 'boolean',
                            ],
                        ],
                    ],
                ],
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
        if (!\is_string($schema)) {
            throw new \RuntimeException('Could not encode OpenAPI DTO test schema.');
        }

        $filesystem->dumpFile($schemaDirectory . '/check-response.json', $schema);

        return $projectRoot;
    }

    private function createGenerator(string $projectRoot, Filesystem $filesystem, ?\DateTimeInterface $generatedAt = null): OpenApiDtoGenerator
    {
        return new OpenApiDtoGenerator(
            new OpenApiDtoSchemaParser(),
            new OpenApiDtoClassRenderer($generatedAt),
            $filesystem,
            ['Framework' => ['path' => $projectRoot . '/src/Core/Framework']],
        );
    }

    private function expectedDtoPath(string $projectRoot): string
    {
        return $projectRoot . '/src/Core/Framework/Api/Dto/CheckResponse.php';
    }
}
