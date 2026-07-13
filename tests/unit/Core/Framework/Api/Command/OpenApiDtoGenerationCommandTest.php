<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Api\Command\OpenApiDtoGenerationCommand;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGeneratedFile;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerationCheckResult;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerationResult;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerator;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoProperty;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(OpenApiDtoGenerationCommand::class)]
#[CoversClass(OpenApiDtoGenerator::class)]
#[CoversClass(OpenApiDtoSchemaParser::class)]
#[CoversClass(OpenApiDtoClassRenderer::class)]
#[CoversClass(OpenApiDtoDefinition::class)]
#[CoversClass(OpenApiDtoProperty::class)]
#[CoversClass(OpenApiDtoGeneratedFile::class)]
#[CoversClass(OpenApiDtoGenerationResult::class)]
#[CoversClass(OpenApiDtoGenerationCheckResult::class)]
class OpenApiDtoGenerationCommandTest extends TestCase
{
    public function testCommandGeneratesDtoFiles(): void
    {
        $filesystem = new Filesystem();
        $projectRoot = $this->createProjectWithSchema($filesystem);
        $tester = new CommandTester($this->createCommand($projectRoot, $filesystem));

        try {
            $status = $tester->execute([]);

            static::assertSame(Command::SUCCESS, $status);
            static::assertStringContainsString('Generated 1 PHP DTO file(s)', $tester->getDisplay());
            static::assertFileExists($this->expectedDtoPath($projectRoot));
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    public function testCommandCheckSucceedsForCurrentGeneratedFiles(): void
    {
        $filesystem = new Filesystem();
        $projectRoot = $this->createProjectWithSchema($filesystem);
        $generator = $this->createGenerator($projectRoot, $filesystem, new \DateTimeImmutable('2026-07-07'));
        $tester = new CommandTester(new OpenApiDtoGenerationCommand(
            $this->createGenerator($projectRoot, $filesystem, new \DateTimeImmutable('2026-07-08')),
        ));

        try {
            $generator->generate();
            $filesystem->dumpFile(
                $this->expectedDtoPath($projectRoot),
                str_replace(
                    'Last generated: 2026-07-07',
                    'Last generated: 2020-01-01',
                    (string) file_get_contents($this->expectedDtoPath($projectRoot)),
                ),
            );

            $status = $tester->execute(['--check' => true]);

            static::assertSame(Command::SUCCESS, $status);
            static::assertStringContainsString('Generated DTO files are up to date', $tester->getDisplay());
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    public function testCommandCheckFailsForMissingGeneratedFiles(): void
    {
        $filesystem = new Filesystem();
        $projectRoot = $this->createProjectWithSchema($filesystem);
        $tester = new CommandTester($this->createCommand($projectRoot, $filesystem));

        try {
            $status = $tester->execute(['--check' => true]);

            static::assertSame(Command::FAILURE, $status);
            static::assertStringContainsString('Generated DTO files are not up to date', $tester->getDisplay());
            static::assertStringContainsString('CheckResponse.php', $tester->getDisplay());
            static::assertStringContainsString('Run bin/console open-api:generate-dtos', $tester->getDisplay());
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    private function createProjectWithSchema(Filesystem $filesystem): string
    {
        $projectRoot = sys_get_temp_dir() . '/open-api-dto-command-' . bin2hex(random_bytes(4));
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

    private function createCommand(string $projectRoot, Filesystem $filesystem): OpenApiDtoGenerationCommand
    {
        return new OpenApiDtoGenerationCommand($this->createGenerator($projectRoot, $filesystem));
    }

    private function createGenerator(string $projectRoot, Filesystem $filesystem, ?ClockInterface $generatedAt = null): OpenApiDtoGenerator
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
