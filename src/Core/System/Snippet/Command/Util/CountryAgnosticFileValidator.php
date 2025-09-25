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
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[Package('discovery')]
class CountryAgnosticFileValidator
{
    public const PLATFORM_DOMAINS = [
        'administration' => 'Administration',
        'messages' => 'Core',
        'storefront' => 'Storefront',
    ];

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    public function checkTranslationFiles(ValidatedTranslationFileOptions $options): ValidatedTranslationFileStruct
    {
        $finder = $this->getFinder($options);
        if ($finder->count() < 1) {
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

            $formatedLanguageStruct = $this->createTranslationFile(
                $currentFileData,
                $file,
                (bool) $isAdminTranslationFile,
                $countrySpecificFileCollection,
            );

            $languageFiles->add($formatedLanguageStruct);
        }

        return $this->processAgnosticFiles(new ValidatedTranslationFileStruct(
            $languageFiles,
            $countrySpecificFileCollection,
        ));
    }

    private function processAgnosticFiles(ValidatedTranslationFileStruct $validatedFileStruct): ValidatedTranslationFileStruct
    {
        $specificCollection = $validatedFileStruct->getSpecificCollection();
        if ($specificCollection->count() === 0) {
            return $validatedFileStruct;
        }

        foreach ($specificCollection as $countrySpecificFile) {
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
        foreach ($validatedFileStruct->getFixingCollection() as $translationFile) {
            $this->filesystem->rename(
                $translationFile->getFullPath(),
                $translationFile->getAgnosticPath(),
            );
        }
    }

    private function getFinder(ValidatedTranslationFileOptions $options): Finder
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
                ...$options->ignoredPaths,
            ])
            ->name([SnippetPatterns::CORE_SNIPPET_FILE_PATTERN, SnippetPatterns::ADMIN_SNIPPET_FILE_PATTERN])
            ->sortByName(true);

        if ($options->dir) {
            $finder->in($options->dir);
        } elseif (empty($options->extensionPaths)) {
            $finder->in('src');

            if ($options->isAll) {
                $finder->in('custom/plugins');
                $finder->in('custom/apps');
            }
        } else {
            $finder->in($this->getExtensionPaths($options));
        }

        return $finder;
    }

    /**
     * @return list<string>
     */
    private function getExtensionPaths(ValidatedTranslationFileOptions $options): array
    {
        return \array_reduce($options->extensionPaths, static function (array $accumulator, string $extension): array {
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
    }

    /**
     * @param array<int|string, string> $currentFileData
     */
    private function createTranslationFile(
        array $currentFileData,
        SplFileInfo $file,
        bool $isAdminTranslationFile,
        TranslationFileCollection $countrySpecificFileCollection
    ): TranslationFile {
        $currentDomain = $currentFileData['domain'] ?? 'administration';
        $formatedLanguageStruct = new TranslationFile(
            $file->getFilename(),
            $file->getPath(),
            $currentDomain,
            str_replace('_', '-', $currentFileData['locale']),
            $currentFileData['language'] ?? null,
            $currentFileData['script'] ?: null,
            $currentFileData['region'] ?: null,
            !$isAdminTranslationFile && !empty($currentFileData['isBase']),
        );

        if ($formatedLanguageStruct->region) {
            $countrySpecificFileCollection->add($formatedLanguageStruct);
        }

        return $formatedLanguageStruct;
    }
}
