<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore Integration tested with \Shopware\Tests\Integration\Core\Framework\App\AppArchiveValidatorTest
 */
#[Package('core')]
class AppArchiveValidator
{
    public function validate(\ZipArchive $archive, ?string $expectedAppName = null): void
    {
        $archiveAppName = $this->guessAppName($archive);

        if ($expectedAppName !== null && $expectedAppName !== $archiveAppName) {
            throw AppArchiveValidationFailure::appNameMismatch($expectedAppName, $archiveAppName);
        }

        $manifest = $archiveAppName . '/manifest.xml';
        $statManifest = $archive->statName($manifest);

        if ($statManifest === false) {
            throw AppArchiveValidationFailure::missingManifest();
        }

        $this->validateAppZip($archiveAppName, $archive);
    }

    private function guessAppName(\ZipArchive $archive): string
    {
        for ($i = 0; $i < $archive->numFiles; ++$i) {
            $entry = $archive->statIndex($i);

            // @codeCoverageIgnoreStart Zip's cannot be empty
            if ($entry === false) {
                throw AppArchiveValidationFailure::appEmpty();
            }
            // @codeCoverageIgnoreEnd

            if (str_contains($entry['name'], '/')) {
                return explode('/', $entry['name'])[0];
            }
        }

        throw AppArchiveValidationFailure::noTopLevelFolder();
    }

    private function validateAppZip(string $prefix, \ZipArchive $archive): void
    {
        for ($i = 0; $i < $archive->numFiles; ++$i) {
            $stat = $archive->statIndex($i);

            \assert($stat !== false);

            $this->assertNoDirectoryTraversal($stat['name']);
            $this->assertPrefix($stat['name'], $prefix);
        }
    }

    private function assertPrefix(string $filename, string $prefix): void
    {
        if (mb_strpos($filename, $prefix) !== 0) {
            throw AppArchiveValidationFailure::invalidPrefix($filename, $prefix);
        }
    }

    private function assertNoDirectoryTraversal(string $filename): void
    {
        if (mb_strpos($filename, '..' . \DIRECTORY_SEPARATOR) !== false) {
            throw AppArchiveValidationFailure::directoryTraversal();
        }
    }
}
