<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerator;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

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

    /**
     * @return iterable<string, array{string}>
     */
    public static function openApiFixtures(): iterable
    {
        foreach (Finder::create()->files()->name('*.json')->notPath('invalidSchemas')->sortByName()->in(__DIR__ . '/_fixtures') as $schemaFile) {
            yield $schemaFile->getRelativePathname() => [$schemaFile->getPathname()];
        }
    }

    #[DataProvider('openApiFixtures')]
    public function testGeneratedFilesMatchAdjacentFixtures(string $schemaFile): void
    {
        $fixtureDirectory = \dirname($schemaFile);
        $frameworkPath = $this->projectDirectory . '/src/Core/Framework';
        $schemaDirectory = $frameworkPath . '/Api/ApiDefinition/Generator/Schema/StoreApi';
        $this->filesystem->mkdir($schemaDirectory);
        $this->filesystem->copy($schemaFile, $schemaDirectory . '/schema.json');
        $generator = new OpenApiDtoGenerator(
            new OpenApiDtoSchemaParser(),
            new OpenApiDtoClassRenderer(new MockClock('2026-07-07 00:00:00')),
            $this->filesystem,
            ['Framework' => ['path' => $frameworkPath]],
        );

        $result = $generator->generate();

        $expectedPaths = [];
        foreach (Finder::create()->files()->depth(0)->name('*.php')->sortByName()->in($fixtureDirectory) as $fixtureFile) {
            $expected = $this->filesystem->readFile($fixtureFile->getPathname());
            static::assertSame(1, preg_match('/^namespace ([^;]+);/m', $expected, $matches));
            $relativePath = str_replace('\\', '/', $matches[1]) . '/' . $fixtureFile->getBasename();
            $relativePath = preg_replace('#^Shopware/#', '', $relativePath);
            $path = $this->projectDirectory . '/src/' . $relativePath;
            $expectedPaths[] = $path;
            static::assertSame(
                preg_replace('/Last generated: [^\r\n]+/', 'Last generated: <generated>', $expected),
                preg_replace('/Last generated: [^\r\n]+/', 'Last generated: <generated>', $this->filesystem->readFile($path)),
                $fixtureFile->getBasename(),
            );
        }

        static::assertNotSame([], $expectedPaths);
        sort($expectedPaths);
        $writtenPaths = $result->writtenFiles;
        sort($writtenPaths);
        static::assertSame($expectedPaths, $writtenPaths);
    }
}
