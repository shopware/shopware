<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command\Util;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\SnippetPatterns;
use Shopware\Core\System\Snippet\Struct\LintedTranslationFileOptions;
use Shopware\Core\System\Snippet\Struct\LintedTranslationFileStruct;
use Shopware\Core\System\Snippet\Struct\TranslationFile;
use Shopware\Core\System\Snippet\Struct\TranslationFileCollection;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('discovery')]
class CountryAgnosticFileLinter
{
    public const PLATFORM_DOMAIN_LABELS = [
        'administration' => 'Administration',
        'messages' => 'Base',
        'storefront' => 'Storefront',
    ];

    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly EntityRepository $pluginRepository,
        private readonly EntityRepository $appRepository,
    ) {
    }

    public function checkTranslationFiles(LintedTranslationFileOptions $options): LintedTranslationFileStruct
    {
        $finder = $this->getFinder($options);
        if ($finder->count() < 1) {
            return new LintedTranslationFileStruct();
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
            $locale = str_replace('_', '-', $currentFileData['locale']);
            $isBase = !$isAdminTranslationFile && !empty($currentFileData['isBase']);

            $translationFile = new TranslationFile(
                $file->getFilename(),
                $file->getPath(),
                $currentDomain,
                $locale,
                $currentFileData['language'],
                $currentFileData['script'] ?? null,
                $currentFileData['region'] ?? null,
                $isBase,
            );

            if ($translationFile->region) {
                $countrySpecificFileCollection->add($translationFile);
            }

            $languageFiles->add($translationFile);
        }

        return $this->processAgnosticFiles(new LintedTranslationFileStruct(
            $languageFiles,
            $countrySpecificFileCollection,
        ));
    }

    public function fixFilenames(LintedTranslationFileStruct $lintedFileStruct): void
    {
        foreach ($lintedFileStruct->getFixingCollection() as $translationFile) {
            $this->filesystem->rename(
                $translationFile->getFullPath(),
                $translationFile->getAgnosticPath(),
            );
        }
    }

    private function processAgnosticFiles(LintedTranslationFileStruct $lintedFileStruct): LintedTranslationFileStruct
    {
        $specificCollection = $lintedFileStruct->getSpecificCollection();
        if ($specificCollection->count() === 0) {
            return $lintedFileStruct;
        }

        $translationCollection = $lintedFileStruct->getCompleteCollection();
        foreach ($specificCollection as $countrySpecificFile) {
            // If no agnostic file exists, $countrySpecificFile is content for `fixFilenames` to be fixed
            if ($translationCollection->get($countrySpecificFile->getAgnosticPath()) === null) {
                $lintedFileStruct->addFixableFile($countrySpecificFile);
            }
        }

        return $lintedFileStruct;
    }

    private function getFinder(LintedTranslationFileOptions $options): Finder
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
                // Translations of languages fetched from crowdin should not be linted
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
                $finder->in('custom');
            }
        } else {
            $finder->in($this->getExtensionPaths($options));
        }

        return $finder;
    }

    /**
     * @return array<string, string>
     */
    private function getExtensionPaths(LintedTranslationFileOptions $options): array
    {
        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('name', $options->extensionPaths));
        $context = Context::createCLIContext();

        $plugins = $this->pluginRepository->search($criteria, $context)->getEntities();
        $apps = $this->appRepository->search($criteria, $context)->getEntities();

        $extensionPaths = [
            ...$plugins->map(static fn (PluginEntity $plugin) => $plugin->getPath()),
            ...$apps->map(static fn (AppEntity $app) => $app->getPath()),
        ];

        if (empty($extensionPaths)) {
            throw SnippetException::invalidExtensions($options->extensionPaths);
        }

        return $extensionPaths;
    }
}
