<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\Filesystem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Validator\Constraints\Locale;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationLoader
{
    public const TRANSLATION_DIR = '/translation';
    public const TRANSLATION_LOCALE_SUB_DIR = 'locale';

    private const PLATFORM_BUNDLES = [
        'Administration' => 'administration.json',
        'Core' => 'messages.json',
        'Storefront' => 'storefront.json',
    ];

    private const PLUGIN_BUNDLES = [
        'Storefront',
        'Administration',
    ];

    /**
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param EntityRepository<LocaleCollection> $localeRepository
     * @param EntityRepository<SnippetSetCollection> $snippetSetRepository
     */
    public function __construct(
        private readonly Filesystem $translationWriter,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $localeRepository,
        private readonly EntityRepository $snippetSetRepository,
        private readonly ClientInterface $client,
        private readonly TranslationConfig $config,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function load(string $locale, Context $context): void
    {
        $language = $this->config->languages->get($locale);

        if (!$language instanceof Language) {
            throw SnippetException::languageDoesNotExist($locale);
        }

        $this->fetchPlatformSnippets($locale);
        $this->fetchPluginSnippets($locale);

        $this->createLanguage($language, $context);
        $this->createSnippetSet($language, $context);
    }

    public function pluginTranslationExists(Plugin $plugin): bool
    {
        $name = $this->config->getMappedPluginName($plugin);
        $localesBasePath = $this->getLocalesBasePath();

        if (!$this->translationWriter->directoryExists($localesBasePath)) {
            return false;
        }

        foreach ($this->translationWriter->listContents($localesBasePath, Filesystem::LIST_DEEP) as $fsNode) {
            if ($fsNode->isDir() && str_ends_with($fsNode->path(), 'Plugins/' . $name)) {
                return true;
            }
        }

        return false;
    }

    public function getLocalesBasePath(): string
    {
        return Path::join(self::TRANSLATION_DIR, self::TRANSLATION_LOCALE_SUB_DIR);
    }

    public function getLocalePath(string $locale): string
    {
        $localeViolationCount = $this->validator
            ->validate($locale, new Locale())
            ->count();
        if ($locale !== '*' && $localeViolationCount !== 0) {
            return '';
        }

        return Path::join(self::TRANSLATION_DIR, self::TRANSLATION_LOCALE_SUB_DIR, $locale);
    }

    private function fetchPluginSnippets(string $locale): void
    {
        foreach ($this->config->plugins as $plugin) {
            foreach (self::PLUGIN_BUNDLES as $bundle) {
                $fileName = strtolower($bundle) . '.json';
                $scope = 'Plugins/' . $plugin;

                $this->fetchFile($bundle, $locale, $fileName, $scope);
            }
        }
    }

    private function fetchPlatformSnippets(string $locale): void
    {
        foreach (self::PLATFORM_BUNDLES as $bundle => $fileName) {
            $this->fetchFile($bundle, $locale, $fileName, 'Platform');
        }
    }

    private function fetchFile(string $bundle, string $locale, string $fileName, string $scope): void
    {
        $destinationPath = Path::join($this->getLocalePath($locale), $scope);

        if (!$this->translationWriter->directoryExists($destinationPath)) {
            $this->translationWriter->createDirectory($destinationPath);
        }

        $downloadUrl = \sprintf(
            '%s/%s/%s/%s/%s',
            $this->config->repositoryUrl,
            $locale,
            $scope,
            $bundle,
            $fileName
        );

        if ($bundle === 'Core') {
            // For the core bundle, we use a specific symfony messages name pattern: messages.{locale}.base.json
            $destinationFileName = 'messages.' . $locale . '.base.json';
        } else {
            // For all other bundles, we use the bundle name e.g. administration.json
            $destinationFileName = strtolower($bundle) . '.json';
        }

        $destination = Path::join($destinationPath, $destinationFileName);

        $this->downloadFile($downloadUrl, $destination);
    }

    private function downloadFile(string $url, string $destination): void
    {
        try {
            $response = $this->client->request('GET', $url);

            $this->translationWriter->write($destination, $response->getBody()->getContents());
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                // If the file does not exist, we can skip it
                return;
            }

            throw $e;
        }
    }

    private function createLanguage(Language $language, Context $context): void
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('code', $language->locale))
            ->setLimit(1);

        $localeId = $this->localeRepository->searchIds($criteria, $context)->firstId();

        if (!$localeId) {
            throw SnippetException::localeDoesNotExist($language->locale);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('localeId', $localeId));

        $languageId = $this->languageRepository->searchIds($criteria, $context)->firstId();

        if (\is_string($languageId)) {
            return;
        }

        $this->languageRepository->create([[
            'name' => $language->name,
            'localeId' => $localeId,
            'translationCodeId' => $localeId,
        ]], $context);
    }

    private function createSnippetSet(Language $language, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $language->locale));

        $snippetId = $this->snippetSetRepository->searchIds($criteria, $context)->firstId();

        if (\is_string($snippetId)) {
            return;
        }

        $snippetSets = [
            [
                'name' => 'BASE ' . $language->locale,
                'iso' => $language->locale,
                'baseFile' => 'messages.' . $language->locale,
            ],
        ];

        $this->snippetSetRepository->create($snippetSets, $context);
    }
}
