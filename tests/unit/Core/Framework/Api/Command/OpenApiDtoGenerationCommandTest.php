<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Api\Command\OpenApiDtoGenerationCommand;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerationCheckResult;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerator;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Shopware\Core\Framework\FrameworkException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
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
            $generatedDto = (string) file_get_contents($this->expectedDtoPath($projectRoot));
            static::assertStringContainsString('Last generated: 2026-07-08 00:00:00', $generatedDto);
            static::assertStringContainsString('use Shopware\\Core\\Framework\\Log\\Package;', $generatedDto);
            static::assertStringContainsString('#[Package(\'framework\')]', $generatedDto);
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    public function testCommandCheckSucceedsForCurrentGeneratedFiles(): void
    {
        $filesystem = new Filesystem();
        $projectRoot = $this->createProjectWithSchema($filesystem);
        $generator = $this->createGenerator($projectRoot, $filesystem, new MockClock('2026-07-07 08:09:10'));
        $tester = new CommandTester(new OpenApiDtoGenerationCommand(
            $this->createGenerator($projectRoot, $filesystem, new MockClock('2026-07-11 11:12:13')),
        ));

        try {
            $generator->generate();
            $filesystem->dumpFile(
                $this->expectedDtoPath($projectRoot),
                $filesystem->readFile($this->expectedDtoPath($projectRoot)),
            );

            $status = $tester->execute(['--check' => true]);

            static::assertSame(Command::SUCCESS, $status);
            static::assertStringContainsString('Generated DTO files are up to date', $tester->getDisplay());
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    public function testCommandGenerationDoesNotRewriteCurrentGeneratedFiles(): void
    {
        $filesystem = new Filesystem();
        $projectRoot = $this->createProjectWithSchema($filesystem);
        $generator = $this->createGenerator($projectRoot, $filesystem, new MockClock('2026-07-07 08:09:10'));
        $tester = new CommandTester(new OpenApiDtoGenerationCommand(
            $this->createGenerator($projectRoot, $filesystem, new MockClock('2026-07-08 11:12:13')),
        ));

        try {
            $generator->generate();
            $initialContents = $filesystem->readFile($this->expectedDtoPath($projectRoot));

            $status = $tester->execute([]);

            static::assertSame(Command::SUCCESS, $status);
            static::assertStringContainsString('Generated 0 PHP DTO file(s)', $tester->getDisplay());
            static::assertSame($initialContents, $filesystem->readFile($this->expectedDtoPath($projectRoot)));
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
            $filesystem->remove($this->expectedDtoPath($projectRoot));

            $status = $tester->execute(['--check' => true]);

            static::assertSame(Command::FAILURE, $status);
            static::assertStringContainsString('Generated DTO files are not up to date', $tester->getDisplay());
            static::assertStringContainsString('CheckResponse.php', $tester->getDisplay());
            static::assertStringContainsString('Run bin/console open-api:generate-dtos', $tester->getDisplay());
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    public function testGeneratorKeepsApiSchemasSeparated(): void
    {
        $filesystem = new Filesystem();
        $projectRoot = $this->createProjectWithConflictingApiSchemas($filesystem);
        $generatedDtoPath = $projectRoot . '/src/Core/Framework/Api/Request/SimpleFilter.php';
        $generatedRequestPath = $projectRoot . '/src/Core/Framework/Api/Request/SearchRequest.php';

        try {
            $this->createGenerator($projectRoot, $filesystem)->generate();

            static::assertFileExists($generatedDtoPath);
            $generatedDto = $filesystem->readFile($generatedDtoPath);
            static::assertStringContainsString('public string $value,', $generatedDto);
            static::assertStringNotContainsString('public array $value,', $generatedDto);
            static::assertFileExists($generatedRequestPath);
            static::assertStringContainsString(
                'public ?SimpleFilter $simpleFilter = null,',
                $filesystem->readFile($generatedRequestPath),
            );
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    public function testGeneratorRejectsConflictingOutputPathsAcrossApis(): void
    {
        $filesystem = new Filesystem();
        $projectRoot = $this->createProjectWithConflictingApiSchemas($filesystem, adminSchemaGeneratesDto: true);
        $generatedDtoPath = $projectRoot . '/src/Core/Framework/Api/Request/SimpleFilter.php';

        try {
            $this->expectExceptionObject(FrameworkException::invalidArgumentException(\sprintf(
                'Admin API and Store API DTO schemas generate conflicting class file "%s".',
                $generatedDtoPath,
            )));

            $this->createGenerator($projectRoot, $filesystem)->generate();
        } finally {
            $filesystem->remove($projectRoot);
        }
    }

    private function createProjectWithConflictingApiSchemas(Filesystem $filesystem, bool $adminSchemaGeneratesDto = false): string
    {
        $projectRoot = sys_get_temp_dir() . '/open-api-dto-command-' . bin2hex(random_bytes(4));
        $schemaDirectory = $projectRoot . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema';

        $this->writeSchema($filesystem, $schemaDirectory . '/StoreApi/components/schemas/simple-filter.json', [
            'paths' => [
                '/search' => [
                    'post' => [
                        'operationId' => 'search',
                        OpenApiDtoGenerator::NAMESPACE_EXTENSION => 'Shopware\\Core\\Framework\\Api\\Request',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'allOf' => [
                                            ['$ref' => '#/components/schemas/SimpleFilter'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'SimpleFilter' => [
                        OpenApiDtoGenerator::NAMESPACE_EXTENSION => 'Shopware\\Core\\Framework\\Api\\Request',
                        OpenApiDtoGenerator::PACKAGE_EXTENSION => 'framework',
                        'type' => 'object',
                        'properties' => [
                            'value' => ['type' => 'string'],
                        ],
                        'required' => ['value'],
                    ],
                ],
            ],
        ]);
        $this->writeSchema($filesystem, $schemaDirectory . '/AdminApi/components/schemas/simple-filter.json', [
            'components' => [
                'schemas' => [
                    'SimpleFilter' => [
                        ...($adminSchemaGeneratesDto ? [
                            OpenApiDtoGenerator::NAMESPACE_EXTENSION => 'Shopware\\Core\\Framework\\Api\\Request',
                            OpenApiDtoGenerator::PACKAGE_EXTENSION => 'framework',
                        ] : []),
                        'anyOf' => [
                            [
                                ...($adminSchemaGeneratesDto ? ['title' => 'SimpleFilter'] : []),
                                'type' => 'object',
                                'properties' => [
                                    'value' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'value' => ['type' => 'boolean'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return $projectRoot;
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
                        OpenApiDtoGenerator::PACKAGE_EXTENSION => 'framework',
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

    /**
     * @param array<string, mixed> $schema
     */
    private function writeSchema(Filesystem $filesystem, string $path, array $schema): void
    {
        $encodedSchema = json_encode($schema, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
        $filesystem->dumpFile($path, $encodedSchema);
    }

    private function createCommand(string $projectRoot, Filesystem $filesystem): OpenApiDtoGenerationCommand
    {
        return new OpenApiDtoGenerationCommand($this->createGenerator($projectRoot, $filesystem));
    }

    private function createGenerator(
        string $projectRoot,
        Filesystem $filesystem,
        ?ClockInterface $generatedAt = null
    ): OpenApiDtoGenerator {
        return new OpenApiDtoGenerator(
            new OpenApiDtoSchemaParser(),
            new OpenApiDtoClassRenderer($generatedAt ?? new MockClock('2026-07-08')),
            $filesystem,
            ['Framework' => ['path' => $projectRoot . '/src/Core/Framework']],
        );
    }

    private function expectedDtoPath(string $projectRoot): string
    {
        return $projectRoot . '/src/Core/Framework/Api/Dto/CheckResponse.php';
    }
}
