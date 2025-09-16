<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\SnippetPatterns;
use Shopware\Core\System\Snippet\Struct\TranslationFile;
use Shopware\Core\System\Snippet\Struct\TranslationFileCollection;
use Shopware\Core\System\Snippet\Struct\ValidatedTranslationFileOptions;
use Shopware\Core\System\Snippet\Struct\ValidatedTranslationFileStruct;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('discovery')]
class CountryAgnosticFileValidator
{
    public const CORE_DOMAINS = [
        'administration' => 'Administration',
        'messages' => 'Core',
        'storefront' => 'Storefront',
    ];

    public function getFinder(ValidatedTranslationFileOptions $options): Finder
    {
        $finder = (new Finder())
            ->files()
            ->ignoreUnreadableDirs()
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->exclude([
                'node_modules',
                'vendor',
                'bin',
                'static',
                // Translations of languages fetched from crowdin should not be validated
                'SwagLanguagePack/src/Resources/snippet',
                'SwagLanguagePack/src/Resources/app/administration/src/snippet',
                ...$options->getIgnoredPaths(),
            ])
            ->name([SnippetPatterns::CORE_SNIPPET_FILE_PATTERN, SnippetPatterns::ADMIN_SNIPPET_FILE_PATTERN])
            ->sortByName(true);

        if ($options->getDir()) {
            $finder->in($options->getDir());
        } elseif (empty($options->getExtensionPaths())) {
            $finder->in('src');

            if ($options->isAll()) {
                $finder->in('custom/plugins');
                $finder->in('custom/apps');
            }
        } else {
            $extensionPaths = \array_reduce($options->getExtensionPaths(), static function (array $accumulator, string $extension): array {
                $extension = trim($extension);
                $appPath = 'custom/apps/' . $extension;
                $pluginPath = 'custom/plugins/' . $extension;

                $isApp = is_dir('custom/apps/' . $extension);
                $isPlugin = is_dir('custom/plugins/' . $extension);

                if (!$isApp && !$isPlugin) {
                    throw SnippetException::invalidExtension($extension);
                }

                if ($isApp) {
                    $accumulator[] = $appPath;
                }

                if ($isPlugin) {
                    $accumulator[] = $pluginPath;
                }

                return $accumulator;
            }, []);

            $finder->in($extensionPaths);
        }

        return $finder;
    }

    public function checkTranslationFiles(ValidatedTranslationFileOptions $options): ValidatedTranslationFileStruct
    {
        $finder = $this->getFinder($options);
        if ($finder->count() < 0) {
            return new ValidatedTranslationFileStruct();
        }

        $languageFiles = new TranslationFileCollection([]);
        $countrySpecificFileCollection = new TranslationFileCollection([]);
        foreach ($finder as $file) {
            $filename = $file->getFilename();

            $isCoreTranslationFile = preg_match(
                SnippetPatterns::CORE_SNIPPET_FILE_PATTERN,
                $filename,
                $coreFileData,
                \PREG_UNMATCHED_AS_NULL
            );

            $isAdminTranslationFile = preg_match(
                SnippetPatterns::ADMIN_SNIPPET_FILE_PATTERN,
                $filename,
                $adminFileData,
                \PREG_UNMATCHED_AS_NULL
            );

            if (!$isAdminTranslationFile && !$isCoreTranslationFile) {
                continue;
            }

            $currentFileData = $isAdminTranslationFile ? $adminFileData : $coreFileData;

            $currentDomain = $currentFileData['domain'] ?? 'administration';
            $formatedLanguageStruct = new TranslationFile(
                $filename,
                $file->getPath(),
                $currentDomain,
                str_replace('_', '-', $currentFileData['locale']),
                $currentFileData['language'] ?? null,
                $currentFileData['script'] ?: null,
                $currentFileData['region'] ?: null,
                !$isAdminTranslationFile && !empty($currentFileData['isBase']),
            );

            $fileIdentifier = $formatedLanguageStruct->getFullPath();

            if ($formatedLanguageStruct->getRegion()) {
                $countrySpecificFileCollection->set($fileIdentifier, $formatedLanguageStruct);
            }

            $languageFiles->set($fileIdentifier, $formatedLanguageStruct);
        }

        return $this->processAgnosticFiles(new ValidatedTranslationFileStruct(
            $languageFiles,
            $countrySpecificFileCollection,
        ));
    }

    public function processAgnosticFiles(ValidatedTranslationFileStruct $validatedFileStruct): ValidatedTranslationFileStruct
    {
        $count = $validatedFileStruct->getSpecificCollection()->count();
        if ($count < 1) {
            return $validatedFileStruct;
        }

        foreach ($validatedFileStruct->getSpecificCollection()->getElements() as $countrySpecificFile) {
            $translationCollection = $validatedFileStruct->getCompleteCollection();

            // If no agnostic file exists, $countrySpecificFile is content for `fixFilenames` to be fixed
            if ($translationCollection->get($countrySpecificFile->getAgnosticPath()) === null) {
                $validatedFileStruct->addFixableFile($countrySpecificFile);
            }
        }

        return $validatedFileStruct;
    }

    public function fixFilenames(ValidatedTranslationFileStruct $validatedFileStruct): void
    {
        $fileSystem = new Filesystem();

        foreach ($validatedFileStruct->getFixingCollection() as $translationFile) {
            $fileSystem->rename(
                $translationFile->getFullPath(),
                $translationFile->getAgnosticPath(),
            );
        }
    }
}
