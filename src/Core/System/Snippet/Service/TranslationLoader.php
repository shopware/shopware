<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\Language;
use Shopware\Core\System\Snippet\Struct\LanguageCollection;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationLoader
{
    private const TRANSLATION_DESTINATION = __DIR__ . '/../../Resources/translation';

    private const TRANSLATION_CONFIG_DIR = __DIR__ . '/../../Resources';

    private const TRANSLATION_CONFIG_FILE = '/translation.yaml';

    private const PLATFORM_DOMAINS = [
        'Administration' => 'administration.json',
        'Core' => 'messages.json',
        'Storefront' => 'storefront.json',
    ];

    private const PLUGIN_DOMAINS = [
        'Storefront',
        'Administration',
    ];

    private TranslationConfig $config;

    private Client $client;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $localeRepository,
        private readonly EntityRepository $snippetSetRepository,
    ) {
        $this->client = new Client();
        $this->config = self::loadConfig();
    }

    public function load(string $locale, Context $context): void
    {
        $this->fetchPluginSnippets($locale);
        $this->fetchPlatformSnippets($locale);

        $language = $this->config->languages->get($locale);
        \assert($language instanceof Language, 'The language for the locale must be defined in the translation config.');

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('code', $language->locale))
            ->setLimit(1);

        $localeId = $this->localeRepository->searchIds($criteria, $context)->firstId();

        $languageId = $this->languageRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('localeId', $localeId)),
            $context
        )->firstId();

        if (!$languageId) {
            $this->languageRepository->upsert([[
                'name' => $language->name,
                'localeId' => $localeId,
                'translationCodeId' => $localeId,
            ]], $context);
        }

        $snippetId = $this->snippetSetRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('iso', $language->locale)),
            $context
        )->firstId();

        if (!$snippetId) {
            $snippetSets = [
                [
                    'name' => $language->name,
                    'iso' => $language->locale,
                    'baseFile' => 'messages.' . $language->locale,
                ],
            ];

            $this->snippetSetRepository->upsert($snippetSets, $context);
        }
    }

    public static function loadConfig(): TranslationConfig
    {
        $path = realpath(self::TRANSLATION_CONFIG_DIR);

        if ($path === false) {
            throw SnippetException::translationConfigurationDirectoryDoesNotExist(self::TRANSLATION_CONFIG_DIR);
        }

        $path .= self::TRANSLATION_CONFIG_FILE;

        $content = file_get_contents($path);

        if ($content === false) {
            throw SnippetException::translationConfigurationFileDoesNotExist(self::TRANSLATION_CONFIG_FILE);
        }

        $config = Yaml::parse($content);

        $url = $config['repository-url'];
        \assert(\is_string($url), 'The repository-url in the translation config must be a string.');

        /** @var list<string> $locales */
        $locales = $config['locales'];
        \assert(\is_array($locales), 'The locales in the translation config must be an array.');

        /** @var list<string> $plugins */
        $plugins = $config['plugins'];
        \assert(\is_array($plugins), 'The plugins in the translation config must be an array.');

        $languages = $config['languages'] ?? [];

        $languageData = [];
        foreach ($languages as $language) {
            $languageData[] = new Language($language['locale'], $language['name']);
        }

        return TranslationConfig::create(
            $url,
            $locales,
            $plugins,
            new LanguageCollection($languageData),
        );
    }

    private function fetchPluginSnippets(string $locale): void
    {
        foreach ($this->config->plugins as $plugin) {
            foreach (self::PLUGIN_DOMAINS as $domain) {
                $fileName = strtolower($domain) . '.json';
                $path = '/' . $locale . '/Plugins/' . $plugin . '/' . $domain;

                $this->fetchFile($path, $fileName);
            }
        }
    }

    private function fetchPlatformSnippets(string $locale): void
    {
        foreach (self::PLATFORM_DOMAINS as $domain => $fileName) {
            $path = '/' . $locale . '/Platform/' . $domain;

            $this->fetchFile($path, $fileName);
        }
    }

    private function fetchFile(string $path, string $fileName): void
    {
        $destinationPath = realpath(self::TRANSLATION_DESTINATION) . $path;

        if (!$this->filesystem->exists($destinationPath)) {
            $this->filesystem->mkdir($destinationPath);
        }

        $url = $this->config->repositoryUrl . $path . '/' . $fileName;

        $this->downloadFile($url, $destinationPath . '/' . $fileName);
    }

    private function downloadFile(string $url, string $destination): void
    {
        try {
            $this->client->request('GET', $url, ['sink' => $destination]);
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                // If the file does not exist, we can skip it
                return;
            }

            throw $e;
        }
    }
}
