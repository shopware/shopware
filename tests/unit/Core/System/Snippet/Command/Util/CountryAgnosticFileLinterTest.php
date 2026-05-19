<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\System\Snippet\Command\Util\CountryAgnosticFileLinter;
use Shopware\Core\System\Snippet\Struct\LintedTranslationFileOptions;
use Shopware\Core\System\Snippet\Struct\LintedTranslationFileStruct;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CountryAgnosticFileLinter::class)]
class CountryAgnosticFileLinterTest extends TestCase
{
    private const FIXTURES_SOURCE_PATH = 'tests/unit/Core/System/Snippet/Command/_fixtures';

    public CountryAgnosticFileLinter $fileLinter;

    private MockObject&Finder $finder;

    /**
     * @var MockObject&EntityRepository<PluginCollection>
     */
    private MockObject&EntityRepository $pluginRepository;

    /**
     * @var MockObject&EntityRepository<AppCollection>
     */
    private MockObject&EntityRepository $appRepository;

    protected function setUp(): void
    {
        // Mock Finder to avoid filesystem scanning
        $this->finder = $this->createMock(Finder::class);
        $filesystem = $this->createMock(Filesystem::class);
        $this->pluginRepository = $this->createMock(EntityRepository::class);
        $this->appRepository = $this->createMock(EntityRepository::class);

        // Configure Finder mock to be chainable
        $this->finder->method('files')->willReturnSelf();
        $this->finder->method('ignoreUnreadableDirs')->willReturnSelf();
        $this->finder->method('ignoreDotFiles')->willReturnSelf();
        $this->finder->method('ignoreVCS')->willReturnSelf();
        $this->finder->method('exclude')->willReturnSelf();
        $this->finder->method('name')->willReturnSelf();
        $this->finder->method('sortByName')->willReturnSelf();
        $this->finder->method('in')->willReturnSelf();

        $this->fileLinter = new CountryAgnosticFileLinter(
            $filesystem,
            $this->pluginRepository,
            $this->appRepository,
            $this->finder,
        );
    }

    public function testCheckTranslationFiles(): void
    {
        // Configure mock Finder to return fake translation files
        $mockFiles = $this->createMockTranslationFiles();
        $this->finder->method('count')->willReturn(\count($mockFiles));
        $this->finder->method('getIterator')->willReturn(new \ArrayIterator($mockFiles));

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['fix', false],
            ['all', false],
            ['extensions', ''],
            ['ignore', ''],
            ['dir', self::FIXTURES_SOURCE_PATH],
        ]);

        $options = LintedTranslationFileOptions::fromInputInterface($input);
        $lintedFileStruct = $this->fileLinter->checkTranslationFiles($options);

        static::assertCount(18, $lintedFileStruct->getCompleteCollection());
        static::assertCount(14, $lintedFileStruct->getSpecificCollection());
        static::assertCount(0, $lintedFileStruct->getDomainCollection('messages'));
        static::assertCount(10, $lintedFileStruct->getDomainCollection('storefront'));
        static::assertCount(10, $lintedFileStruct->getDomainCollection('sth-which-fallbacks-to-storefront'));
        static::assertCount(8, $lintedFileStruct->getDomainCollection('administration'));

        static::assertCount(6, $lintedFileStruct->getFixableFiles()->getMapping());
        static::assertCount(9, $lintedFileStruct->getFixableFiles());
        static::assertCount(0, $lintedFileStruct->getFixingCollection());
    }

    public function testFixFilenames(): void
    {
        // Configure mock Finder to return fake translation files
        $mockFiles = $this->createMockTranslationFiles();
        $this->finder->method('count')->willReturn(\count($mockFiles));
        $this->finder->method('getIterator')->willReturn(new \ArrayIterator($mockFiles));

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['fix', true],
            ['all', false],
            ['extensions', ''],
            ['ignore', ''],
            ['dir', self::FIXTURES_SOURCE_PATH],
        ]);

        $options = LintedTranslationFileOptions::fromInputInterface($input);
        $lintedFileStruct = $this->fileLinter->checkTranslationFiles($options);
        $hydratedFileStruct = $this->hydrateFixingCollection($lintedFileStruct);
        $this->fileLinter->fixFilenames($hydratedFileStruct);

        static::assertCount(18, $hydratedFileStruct->getCompleteCollection());
        static::assertCount(14, $hydratedFileStruct->getSpecificCollection());
        static::assertCount(0, $hydratedFileStruct->getDomainCollection('messages'));
        static::assertCount(10, $hydratedFileStruct->getDomainCollection('storefront'));
        static::assertCount(10, $hydratedFileStruct->getDomainCollection('sth-which-fallbacks-to-storefront'));
        static::assertCount(8, $hydratedFileStruct->getDomainCollection('administration'));

        static::assertCount(6, $hydratedFileStruct->getFixableFiles()->getMapping());
        static::assertCount(9, $hydratedFileStruct->getFixableFiles());
        static::assertCount(6, $hydratedFileStruct->getFixingCollection());
    }

    /**
     * @return \Generator<string, array{dir: string, isAll: bool, expectedPaths: array<string>, callCount: int}>
     */
    public static function getFinderPathProvider(): \Generator
    {
        yield 'custom directory' => [
            'dir' => '/custom/path',
            'isAll' => false,
            'expectedPaths' => ['/custom/path'],
            'callCount' => 1,
        ];

        yield 'default src directory' => [
            'dir' => '',
            'isAll' => false,
            'expectedPaths' => ['src'],
            'callCount' => 1,
        ];

        yield 'all option includes custom' => [
            'dir' => '',
            'isAll' => true,
            'expectedPaths' => ['src', 'custom'],
            'callCount' => 2,
        ];
    }

    /**
     * @param array<string> $expectedPaths
     */
    #[DataProvider('getFinderPathProvider')]
    public function testGetFinderWithDifferentPaths(string $dir, bool $isAll, array $expectedPaths, int $callCount): void
    {
        $this->finder->expects($this->exactly($callCount))
            ->method('in')
            ->willReturnCallback(function ($path) use ($expectedPaths) {
                static::assertContains($path, $expectedPaths);

                return $this->finder;
            });

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['fix', false],
            ['all', $isAll],
            ['extensions', ''],
            ['ignore', ''],
            ['dir', $dir],
        ]);

        $options = LintedTranslationFileOptions::fromInputInterface($input);

        // Mock empty result
        $this->finder->method('count')->willReturn(0);
        $this->finder->method('getIterator')->willReturn(new \ArrayIterator([]));

        $result = $this->fileLinter->checkTranslationFiles($options);

        $this->assertEmptyResult($result);
    }

    public function testGetFinderWithExtensionPaths(): void
    {
        $pluginSearchResult = $this->createPluginSearchResult();
        $appSearchResult = $this->createAppSearchResult();

        $this->pluginRepository->expects($this->once())->method('search')->willReturn($pluginSearchResult);
        $this->appRepository->expects($this->once())->method('search')->willReturn($appSearchResult);

        // Verify that Finder->in() is called with an array containing both paths
        // The exact structure depends on entity IDs from map(), so we check values
        $this->finder->expects($this->once())
            ->method('in')
            ->willReturnCallback(function ($paths) {
                $pathValues = array_values($paths);
                static::assertContains('/path/to/plugin1', $pathValues);
                static::assertContains('/path/to/app1', $pathValues);

                return $this->finder;
            });

        $options = $this->createOptionsWithExtensions();

        $this->finder->method('count')->willReturn(0);
        $this->finder->method('getIterator')->willReturn(new \ArrayIterator([]));

        $result = $this->fileLinter->checkTranslationFiles($options);
        $this->assertEmptyResult($result);
    }

    /**
     * @return EntitySearchResult<PluginCollection>
     */
    private function createPluginSearchResult(): EntitySearchResult
    {
        $plugin = new PluginEntity();
        $plugin->setPath('/path/to/plugin1');
        $plugin->setUniqueIdentifier('plugin-id-1');

        $collection = new PluginCollection([$plugin]);

        return new EntitySearchResult(
            'plugin',
            1,
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntitySearchResult<AppCollection>
     */
    private function createAppSearchResult(): EntitySearchResult
    {
        $app = new AppEntity();
        $app->setPath('/path/to/app1');
        $app->setUniqueIdentifier('app-id-1');

        $collection = new AppCollection([$app]);

        return new EntitySearchResult(
            'app',
            1,
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function createOptionsWithExtensions(): LintedTranslationFileOptions
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['fix', false],
            ['all', false],
            ['extensions', 'MyPlugin,MyApp'],
            ['ignore', ''],
            ['dir', ''],
        ]);

        return LintedTranslationFileOptions::fromInputInterface($input);
    }

    private function assertEmptyResult(LintedTranslationFileStruct $result): void
    {
        static::assertCount(0, $result->getCompleteCollection(), 'Should have no files when Finder returns empty result');
        static::assertCount(0, $result->getSpecificCollection(), 'Should have no country-specific files');
        static::assertCount(0, $result->getFixableFiles(), 'Should have no fixable files');
    }

    private function hydrateFixingCollection(LintedTranslationFileStruct $lintedFileStruct): LintedTranslationFileStruct
    {
        foreach ($lintedFileStruct->getFixableFiles()->getMapping() as $fileOptions) {
            $firstFileOption = array_first($fileOptions);
            static::assertNotNull($firstFileOption);
            $lintedFileStruct->addToFixingCollection($firstFileOption);
        }

        return $lintedFileStruct;
    }

    /**
     * @return array<SplFileInfo>
     */
    private function createMockTranslationFiles(): array
    {
        $basePath = self::FIXTURES_SOURCE_PATH;
        $mockFiles = [];

        // Root directory files
        // Administration files (base path)
        $mockFiles[] = $this->createMockFile('be-BE.json', $basePath);
        $mockFiles[] = $this->createMockFile('be.json', $basePath);
        $mockFiles[] = $this->createMockFile('jp-JP.json', $basePath);
        $mockFiles[] = $this->createMockFile('nl-BE.json', $basePath);
        $mockFiles[] = $this->createMockFile('nl-NL.json', $basePath);

        // Storefront files (base path)
        $mockFiles[] = $this->createMockFile('storefront.de-DE.json', $basePath);
        $mockFiles[] = $this->createMockFile('storefront.de.json', $basePath);
        $mockFiles[] = $this->createMockFile('storefront.fr-BE.json', $basePath);
        $mockFiles[] = $this->createMockFile('storefront.fr-FR.json', $basePath);
        $mockFiles[] = $this->createMockFile('storefront.it-IT.json', $basePath);

        // Subdirectory files
        $subPath = $basePath . '/subdir';
        // Administration files (subdir)
        $mockFiles[] = $this->createMockFile('hr-HR.json', $subPath);
        $mockFiles[] = $this->createMockFile('hr.json', $subPath);
        $mockFiles[] = $this->createMockFile('ko-KR.json', $subPath);

        // Storefront files (subdir)
        $mockFiles[] = $this->createMockFile('storefront.en-GB.json', $subPath);
        $mockFiles[] = $this->createMockFile('storefront.en-US.json', $subPath);
        $mockFiles[] = $this->createMockFile('storefront.en.json', $subPath);
        $mockFiles[] = $this->createMockFile('storefront.es-AR.json', $subPath);
        $mockFiles[] = $this->createMockFile('storefront.es-ES.json', $subPath);

        return $mockFiles;
    }

    private function createMockFile(string $filename, string $path): SplFileInfo
    {
        return new SplFileInfo($path . '/' . $filename, $path, $filename);
    }
}
