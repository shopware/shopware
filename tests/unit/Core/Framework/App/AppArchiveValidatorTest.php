<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppArchiveValidator::class)]
class AppArchiveValidatorTest extends TestCase
{
    public function testExceptionIsThrownWhenManifestIsMissing(): void
    {
        $archive = $this->openArchive('AppWithoutManifestFile.zip');

        $validator = new AppArchiveValidator();

        $this->expectExceptionObject(AppArchiveValidationFailure::missingManifest());

        $validator->validate($archive, 'TestApp');
    }

    public function testExceptionIsThrownWhenAppNameDoesNotMatch(): void
    {
        $archive = $this->openArchive('App.zip');

        $validator = new AppArchiveValidator();

        $this->expectExceptionObject(AppArchiveValidationFailure::appNameMismatch('WrongName', 'TestApp'));

        $validator->validate($archive, 'WrongName');
    }

    public function testExceptionIsThrownWhenArchiveHasInvalidPrefix(): void
    {
        $archive = $this->openArchive('AppInvalidPrefix.zip');

        $validator = new AppArchiveValidator();

        $this->expectExceptionObject(AppArchiveValidationFailure::invalidPrefix('some-file.txt', 'TestApp'));

        $validator->validate($archive, 'TestApp');
    }

    public function testExceptionIsThrownWhenArchiveHasNoTopLevelFolder(): void
    {
        $archive = $this->openArchive('AppNoTobLevelFolder.zip');

        $validator = new AppArchiveValidator();

        $this->expectExceptionObject(AppArchiveValidationFailure::noTopLevelFolder());

        $validator->validate($archive, 'TestApp');
    }

    public function testExceptionIsThrownOnDirectoryTraversal(): void
    {
        $archive = $this->openArchive('FileTraversal.zip');

        $validator = new AppArchiveValidator();

        $this->expectExceptionObject(AppArchiveValidationFailure::directoryTraversal());

        $validator->validate($archive, 'TestApp');
    }

    public function testWithValidArchive(): void
    {
        $archive = $this->openArchive('App.zip');
        $validator = new AppArchiveValidator();

        $this->expectNotToPerformAssertions();

        $validator->validate($archive, 'TestApp');
    }

    public function testWithValidArchiveWithoutExpectedName(): void
    {
        $archive = $this->openArchive('App.zip');
        $validator = new AppArchiveValidator();

        $this->expectNotToPerformAssertions();

        $validator->validate($archive);
    }

    private function openArchive(string $fixture): \ZipArchive
    {
        $archive = new \ZipArchive();
        $archive->open(__DIR__ . '/_fixtures/' . $fixture, \ZipArchive::RDONLY);

        return $archive;
    }
}
