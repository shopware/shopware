<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\CountryAgnosticFileLinter;
use Shopware\Core\System\Snippet\Struct\ValidatedTranslationFileOptions;
use Shopware\Core\System\Snippet\Struct\ValidatedTranslationFileStruct;
use Symfony\Component\Filesystem\Filesystem;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('slow')]
#[CoversClass(CountryAgnosticFileLinter::class)]
class CountryAgnosticFileLinterTest extends TestCase
{
    private const FIXTURES_SOURCE_PATH = 'tests/unit/Core/System/Snippet/Command/_fixtures';
    private const FIXTURES_PATH = self::FIXTURES_SOURCE_PATH . '/../temp';

    public CountryAgnosticFileLinter $fileLinter;

    protected function setUp(): void
    {
        $filesystem = new Filesystem();
        $filesystem->mirror(self::FIXTURES_SOURCE_PATH, self::FIXTURES_PATH);

        $this->fileLinter = new CountryAgnosticFileLinter(
            $filesystem,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(self::FIXTURES_PATH);
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
        $validatedFileStruct = $this->fileLinter->checkTranslationFiles($options);

        static::assertCount(18, $validatedFileStruct->getCompleteCollection());
        static::assertCount(14, $validatedFileStruct->getSpecificCollection());
        static::assertCount(0, $validatedFileStruct->getDomainCollection('messages'));
        static::assertCount(10, $validatedFileStruct->getDomainCollection('storefront'));
        static::assertCount(10, $validatedFileStruct->getDomainCollection('sth-which-fallbacks-to-storefront'));
        static::assertCount(8, $validatedFileStruct->getDomainCollection('administration'));

        static::assertCount(6, $validatedFileStruct->getFixableFiles()->getMapping());
        static::assertCount(9, $validatedFileStruct->getFixableFiles());
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
        $validatedFileStruct = $this->fileLinter->checkTranslationFiles($options);
        $hydratedFileStruct = $this->hydrateFixingCollection($validatedFileStruct);
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

    private function hydrateFixingCollection(ValidatedTranslationFileStruct $validatedFileStruct): ValidatedTranslationFileStruct
    {
        foreach ($validatedFileStruct->getFixableFiles()->getMapping() as $fileOptions) {
            $selection = array_key_first($fileOptions);
            $validatedFileStruct->addToFixingCollection($fileOptions[$selection]);
        }

        return $validatedFileStruct;
    }
}
