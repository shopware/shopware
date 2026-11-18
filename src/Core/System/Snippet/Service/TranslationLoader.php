<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\Event\TranslationLoadedEvent;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\SnippetPatterns;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationLoader extends AbstractTranslationLoader implements ResetInterface
{
    private const CONCURRENT_DOWNLOADS = 10;

    /**
     * @var ArrayStruct<list<string>>|null
     */
    private ?ArrayStruct $existingPluginLocaleTranslations = null;

    /**
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param EntityRepository<LocaleCollection> $localeRepository
     * @param EntityRepository<SnippetSetCollection> $snippetSetRepository
     */
    public function __construct(
        private readonly FilesystemOperator $translationWriter,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $localeRepository,
        private readonly EntityRepository $snippetSetRepository,
        private readonly ClientInterface $client,
        private readonly TranslationConfig $config,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractTranslationLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $locale, Context $context, bool $activate = true): void
    {
        $language = $this->resolveLanguage($locale);

        $this->download($locale);

        $this->createLanguage($language, $context, $activate);
        $this->createSnippetSet($language, $context);

        $this->eventDispatcher->dispatch(new TranslationLoadedEvent($locale, $context));
    }

    /**
     * Creates the language and snippet set for translation files that are already on the
     * filesystem, without contacting the translation repository.
     */
    public function link(string $locale, Context $context, bool $activate = true): void
    {
        $language = $this->resolveLanguage($locale);

        if (!$this->hasTranslationFiles($locale)) {
            throw SnippetException::translationsUnavailable([$locale]);
        }

        $this->createLanguage($language, $context, $activate);
        $this->createSnippetSet($language, $context);

        $this->eventDispatcher->dispatch(new TranslationLoadedEvent($locale, $context));
    }

    /**
     * An empty or aborted download leaves the locale directories behind, so only a file counts.
     * Any file counts, because a legitimate load() can produce a partial set.
     */
    public function hasTranslationFiles(string $locale): bool
    {
        $localePath = $this->getLocalePath($locale);

        if ($localePath === '') {
            return false;
        }

        foreach ($this->translationWriter->listContents($localePath, FilesystemOperator::LIST_DEEP) as $fsNode) {
            if ($fsNode->isFile()) {
                return true;
            }
        }

        return false;
    }

    public function download(string $locale): void
    {
        if (!$this->config->languages->has($locale)) {
            throw SnippetException::languageDoesNotExist($locale);
        }

        $this->downloadFiles($this->prepareDownloads($locale));

        // New plugin translation directories may have been written, invalidate the memoized lookup.
        $this->reset();
    }

    public function pluginTranslationExists(Plugin $plugin): bool
    {
        $this->memoizePluginLocaleTranslations();

        $name = $this->config->getMappedPluginName($plugin);

        return $this->existingPluginLocaleTranslations?->has($name) === true;
    }

    public function pluginTranslationExistsForLocale(Plugin $plugin, string $locale): bool
    {
        $this->memoizePluginLocaleTranslations();

        $name = $this->config->getMappedPluginName($plugin);
        $localesByPlugin = $this->existingPluginLocaleTranslations?->get($name);

        if (!\is_array($localesByPlugin)) {
            return false;
        }

        return \in_array(\strtolower($locale), $localesByPlugin, true);
    }

    public function reset(): void
    {
        $this->existingPluginLocaleTranslations = null;
    }

    public function getLocalesBasePath(): string
    {
        return Path::join(static::TRANSLATION_DIR, static::TRANSLATION_LOCALE_SUB_DIR);
    }

    public function getLocalePath(string $locale): string
    {
        if (
            $locale !== '*'
            && !\array_key_exists($locale, SnippetPatterns::ALLOWED_PSEUDO_LOCALES)
            && !preg_match(SnippetPatterns::COMPLETE_LOCALE_PATTERN, $locale)
        ) {
            return '';
        }

        return Path::join(static::TRANSLATION_DIR, static::TRANSLATION_LOCALE_SUB_DIR, $locale);
    }

    private function resolveLanguage(string $locale): Language
    {
        $language = $this->config->languages->get($locale);

        if (!$language instanceof Language) {
            throw SnippetException::languageDoesNotExist($locale);
        }

        return $language;
    }

    private function memoizePluginLocaleTranslations(): void
    {
        if ($this->existingPluginLocaleTranslations !== null) {
            return;
        }

        $localesBasePath = $this->getLocalesBasePath();
        /** @var ArrayStruct<list<string>> $pluginLocales */
        $pluginLocales = new ArrayStruct();

        foreach ($this->translationWriter->listContents($localesBasePath, FilesystemOperator::LIST_DEEP) as $fsNode) {
            if (\preg_match('#(?P<locale>[^/]+)/Plugins/(?P<plugin>[^/]+)#', $fsNode->path(), $matches) !== 1) {
                continue;
            }

            $locales = $pluginLocales->get($matches['plugin']) ?? [];
            $locales[] = \strtolower($matches['locale']);

            $pluginLocales->set($matches['plugin'], \array_unique($locales));
        }

        $this->existingPluginLocaleTranslations = $pluginLocales;
    }

    /**
     * @return array<string, string>
     */
    private function prepareDownloads(string $locale): array
    {
        $destinationByUrl = [];

        foreach (TranslationFileMap::forLocale($this->config, $locale, $this->getLocalePath($locale)) as $remotePath => $destination) {
            $destinationDirectory = Path::getDirectory($destination);

            if (!$this->translationWriter->directoryExists($destinationDirectory)) {
                $this->translationWriter->createDirectory($destinationDirectory);
            }

            $destinationByUrl[$this->config->repositoryUrl . '/' . $remotePath] = $destination;
        }

        return $destinationByUrl;
    }

    /**
     * @param array<string, string> $destinationByUrl
     */
    private function downloadFiles(array $destinationByUrl): void
    {
        $downloadFailure = null;

        $requests = function () use ($destinationByUrl): \Generator {
            foreach ($destinationByUrl as $url => $destination) {
                yield fn (): PromiseInterface => $this->client->requestAsync('GET', $url)->then(
                    fn (ResponseInterface $response) => $this->translationWriter->write($destination, $response->getBody()->getContents())
                );
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => self::CONCURRENT_DOWNLOADS,
            'rejected' => static function (mixed $reason) use (&$downloadFailure): void {
                if ($reason instanceof GuzzleException && $reason->getCode() === 404) {
                    return;
                }

                $downloadFailure ??= Create::exceptionFor($reason);
            },
        ]);

        $pool->promise()->wait();

        if ($downloadFailure instanceof \Throwable) {
            throw $downloadFailure;
        }
    }

    private function createLanguage(Language $language, Context $context, bool $activate = true): void
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('code', $language->locale))
            ->setLimit(1);

        $localeId = $this->localeRepository->searchIds($criteria, $context)->firstId();

        if (!$localeId) {
            if (!\array_key_exists($language->locale, SnippetPatterns::ALLOWED_PSEUDO_LOCALES)) {
                throw SnippetException::localeDoesNotExist($language->locale);
            }

            $localeId = $this->createPseudoLocale($language, $context);
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
            'active' => $activate,
        ]], $context);
    }

    private function createPseudoLocale(Language $language, Context $context): string
    {
        $localeId = Uuid::randomHex();

        $this->localeRepository->create([[
            'id' => $localeId,
            'code' => $language->locale,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => SnippetPatterns::ALLOWED_PSEUDO_LOCALES[$language->locale],
                    'territory' => SnippetPatterns::PSEUDO_LOCALE_TERRITORY,
                ],
            ],
        ]], $context);

        return $localeId;
    }

    private function createSnippetSet(Language $language, Context $context): void
    {
        $snippetSetName = "BASE {$language->locale}";

        $criteria = new Criteria();
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('iso', $language->locale),
                new EqualsFilter('name', $snippetSetName),
            ])
        );

        $snippetId = $this->snippetSetRepository->searchIds($criteria, $context)->firstId();

        if (\is_string($snippetId)) {
            return;
        }

        $snippetSets = [
            [
                'name' => $snippetSetName,
                'iso' => $language->locale,
                'baseFile' => 'messages.' . $language->locale,
            ],
        ];

        $this->snippetSetRepository->create($snippetSets, $context);
    }
}
