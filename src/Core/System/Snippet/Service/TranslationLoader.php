<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\Language;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationLoader
{
    public const TRANSLATION_DESTINATION = __DIR__ . '/../../Resources/translation';

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
        private readonly Filesystem $filesystem,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $localeRepository,
        private readonly EntityRepository $snippetSetRepository,
        private readonly ClientInterface $client,
        private readonly TranslationConfig $config,
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

    public static function pluginTranslationExists(Plugin $plugin): bool
    {
        $name = TranslationConfigLoader::getMappedPluginName($plugin);
        $pattern = self::TRANSLATION_DESTINATION . '/*/Plugins/' . $name;

        return (bool) glob($pattern, \GLOB_ONLYDIR);
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
        $destinationPath = \sprintf('%s/%s/%s/', realpath(self::TRANSLATION_DESTINATION), $locale, $scope);

        if (!$this->filesystem->exists($destinationPath)) {
            $this->filesystem->mkdir($destinationPath);
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

        $destination = $destinationPath . $destinationFileName;

        $this->downloadFile($downloadUrl, $destination);
    }

    private function downloadFile(string $url, string $destination): void
    {
        try {
            $response = $this->client->request('GET', $url);

            file_put_contents($destination, $response->getBody());
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
