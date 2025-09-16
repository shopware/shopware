<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\CountryAgnosticFileValidator;
use Shopware\Core\System\Snippet\Struct\ValidatedTranslationFileOptions;
use Shopware\Core\System\Snippet\Struct\ValidatedTranslationFileStruct;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('slow')]
#[CoversClass(CountryAgnosticFileValidator::class)]
class CountryAgnosticFileValidatorTest extends TestCase
{
    private const FIXTURES_SOURCE_PATH = 'tests/unit/Core/System/Snippet/Command/_fixtures';
    private const FIXTURES_PATH = self::FIXTURES_SOURCE_PATH . '/../temp';
    private const FIXTURES_SUBDIRECTORY = 'subdir';

    public CountryAgnosticFileValidator $fileValidator;

    protected function setUp(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_SOURCE_PATH, self::FIXTURES_PATH);
        $this->fileValidator = new CountryAgnosticFileValidator();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(self::FIXTURES_PATH);
    }

    /**
     * @return \Generator<string, array<string, bool>>
     */
    public static function getFinderDataProviderWithDefaultFiles(): \Generator
    {
        yield 'Options: none' => [
            'allParameter' => false,
        ];

        yield 'Options: all' => [
            'allParameter' => true,
        ];
    }

    #[DataProvider('getFinderDataProviderWithDefaultFiles')]
    public function testGetFinderDataProviderWithDefaultFiles(bool $allParameter): void
    {
        $options = new ValidatedTranslationFileOptions(false, $allParameter);
        $finder = $this->fileValidator->getFinder($options);
        $files = iterator_to_array($finder->getIterator());

        static::assertTrue(\count($files) > 100, \sprintf(
            'Expected to find more than 100 files %s enabled "all" parameter',
            $allParameter ? 'with' : 'without'
        ));
    }

    /**
     * @return \Generator<string, array{
     *     ignoredPaths: list<string>,
     *     expectedCount: int
     * }>
     */
    public static function getFinderDataProviderWithDirDataProvider(): \Generator
    {
        yield 'All fixtures' => [
            'ignoredPaths' => [],
            'expectedCount' => 18,
        ];

        yield 'All fixtures without sub directory' => [
            'ignoredPaths' => [self::FIXTURES_SUBDIRECTORY],
            'expectedCount' => 10,
        ];
    }

    /**
     * @param list<string> $ignoredPaths
     */
    #[DataProvider('getFinderDataProviderWithDirDataProvider')]
    public function testGetFinderDataProviderWithDir(array $ignoredPaths, int $expectedCount): void
    {
        $options = new ValidatedTranslationFileOptions(
            false,
            false,
            [],
            $ignoredPaths,
            self::FIXTURES_PATH,
        );
        $finder = $this->fileValidator->getFinder($options);
        $files = iterator_to_array($finder->getIterator());

        static::assertCount($expectedCount, $files);
    }

    public function testCheckTranslationFiles(): void
    {
        $options = new ValidatedTranslationFileOptions(
            false,
            false,
            [],
            [],
            self::FIXTURES_PATH,
        );
        $validatedFileStruct = $this->fileValidator->checkTranslationFiles($options);

        static::assertCount(18, $validatedFileStruct->getCompleteCollection());
        static::assertCount(14, $validatedFileStruct->getSpecificCollection());
        static::assertCount(0, $validatedFileStruct->getDomainCollection('messages'));
        static::assertCount(10, $validatedFileStruct->getDomainCollection('storefront'));
        static::assertCount(10, $validatedFileStruct->getDomainCollection('sth-which-fallbacks-to-storefront'));
        static::assertCount(8, $validatedFileStruct->getDomainCollection('administration'));

        static::assertCount(6, $validatedFileStruct->getFixableFiles());
        static::assertCount(9, $validatedFileStruct->getIssues());
        static::assertCount(0, $validatedFileStruct->getFixingCollection());
    }

    public function testFixFilenames(): void
    {
        $options = new ValidatedTranslationFileOptions(
            true,
            false,
            [],
            [],
            self::FIXTURES_PATH,
        );
        $validatedFileStruct = $this->fileValidator->checkTranslationFiles($options);
        $hydratedFileStruct = $this->hydrateFixingCollection($validatedFileStruct);
        $this->fileValidator->fixFilenames($hydratedFileStruct);

        static::assertCount(18, $hydratedFileStruct->getCompleteCollection());
        static::assertCount(14, $hydratedFileStruct->getSpecificCollection());
        static::assertCount(0, $hydratedFileStruct->getDomainCollection('messages'));
        static::assertCount(10, $hydratedFileStruct->getDomainCollection('storefront'));
        static::assertCount(10, $hydratedFileStruct->getDomainCollection('sth-which-fallbacks-to-storefront'));
        static::assertCount(8, $hydratedFileStruct->getDomainCollection('administration'));

        static::assertCount(6, $hydratedFileStruct->getFixableFiles());
        static::assertCount(9, $hydratedFileStruct->getIssues());
        static::assertCount(6, $hydratedFileStruct->getFixingCollection());
    }

    private function hydrateFixingCollection(ValidatedTranslationFileStruct $validatedFileStruct): ValidatedTranslationFileStruct
    {
        foreach ($validatedFileStruct->getFixableFiles() as $fileOptions) {
            $selection = array_key_first($fileOptions);
            $validatedFileStruct->addToFixingCollection($fileOptions[$selection]);
        }

        return $validatedFileStruct;
    }
}
