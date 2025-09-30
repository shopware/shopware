<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\CountryAgnosticFileLinter;
use Shopware\Core\System\Snippet\Struct\LintedTranslationFileOptions;
use Shopware\Core\System\Snippet\Struct\LintedTranslationFileStruct;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

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
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['fix', false],
            ['all', false],
            ['extensions', ''],
            ['ignore', ''],
            ['dir', self::FIXTURES_PATH],
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
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnMap([
            ['fix', true],
            ['all', false],
            ['extensions', ''],
            ['ignore', ''],
            ['dir', self::FIXTURES_PATH],
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

    private function hydrateFixingCollection(LintedTranslationFileStruct $lintedFileStruct): LintedTranslationFileStruct
    {
        foreach ($lintedFileStruct->getFixableFiles()->getMapping() as $fileOptions) {
            $selection = array_key_first($fileOptions);
            $lintedFileStruct->addToFixingCollection($fileOptions[$selection]);
        }

        return $lintedFileStruct;
    }
}
