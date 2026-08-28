<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Snippet\Command;

use GuzzleHttp\ClientInterface;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\Command\InstallTranslationCommand;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Tests\Integration\Core\System\Snippet\TranslationClientBehaviour;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Covers `translation:install --offline` against the real database.
 *
 * The loader is built on an own filesystem root so the fixtures stay out of the translation files of
 * the installation this runs in. The locales are picked at runtime from the ones this installation has
 * not installed yet, because a language cannot be deleted to get a clean state: that also rewrites the
 * community translation metadata, outside the test transaction.
 *
 * @internal
 */
#[Package('discovery')]
class InstallTranslationCommandTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use TranslationClientBehaviour;

    private string $provisionedLocale;

    private string $provisionedLanguageName;

    private string $unprovisionedLocale;

    private string $translationRoot;

    private Flysystem $translationFilesystem;

    private TranslationLoader $translationLoader;

    private TranslationConfig $config;

    private Context $context;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    /**
     * @var EntityRepository<LocaleCollection>
     */
    private EntityRepository $localeRepository;

    /**
     * @var EntityRepository<SnippetSetCollection>
     */
    private EntityRepository $snippetSetRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->languageRepository = static::getContainer()->get('language.repository');
        $this->snippetSetRepository = static::getContainer()->get('snippet_set.repository');
        $this->localeRepository = static::getContainer()->get('locale.repository');

        $config = static::getContainer()->get(TranslationConfig::class);
        static::assertInstanceOf(TranslationConfig::class, $config);
        $this->config = $config;

        $this->translationRoot = Path::join(sys_get_temp_dir(), 'sw-translation-' . Uuid::randomHex());
        $this->translationFilesystem = new Flysystem(new LocalFilesystemAdapter($this->translationRoot));

        [$provisioned, $unprovisioned] = $this->findInstallableLanguages();
        $this->provisionedLocale = $provisioned->locale;
        $this->provisionedLanguageName = $provisioned->name;
        $this->unprovisionedLocale = $unprovisioned->locale;

        $client = static::getContainer()->get('shopware.translation.client');
        static::assertInstanceOf(ClientInterface::class, $client);

        $this->translationLoader = new TranslationLoader(
            translationWriter: $this->translationFilesystem,
            languageRepository: $this->languageRepository,
            localeRepository: $this->localeRepository,
            snippetSetRepository: $this->snippetSetRepository,
            client: $client,
            config: $this->config,
            eventDispatcher: static::getContainer()->get('event_dispatcher'),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->translationRoot);
    }

    public function testOfflineInstallCreatesLanguageAndSnippetSetFromFilesOnDisk(): void
    {
        $this->provideTranslationFile($this->provisionedLocale);

        $this->executeOfflineInstall([$this->provisionedLocale])->assertCommandIsSuccessful();

        $languages = $this->findLanguages($this->provisionedLocale);
        static::assertCount(1, $languages);

        $language = $languages->first();
        static::assertNotNull($language);
        static::assertSame($this->provisionedLanguageName, $language->getName());
        static::assertTrue($language->isActive());
        static::assertSame($language->getLocaleId(), $language->getTranslationCodeId());

        $snippetSets = $this->findSnippetSets($this->provisionedLocale, 'BASE ' . $this->provisionedLocale);
        static::assertCount(1, $snippetSets);

        $snippetSet = $snippetSets->first();
        static::assertNotNull($snippetSet);
        static::assertSame('messages.' . $this->provisionedLocale, $snippetSet->getBaseFile());
    }

    public function testOfflineInstallCreatesInactiveLanguageWithSkipActivation(): void
    {
        $this->provideTranslationFile($this->provisionedLocale);

        $this->executeOfflineInstall([$this->provisionedLocale], skipActivation: true)->assertCommandIsSuccessful();

        $language = $this->findLanguages($this->provisionedLocale)->first();
        static::assertNotNull($language);
        static::assertFalse($language->isActive());
        static::assertCount(1, $this->findSnippetSets($this->provisionedLocale, 'BASE ' . $this->provisionedLocale));
    }

    public function testOfflineInstallLeavesExistingEntitiesUntouched(): void
    {
        $this->provideTranslationFile($this->provisionedLocale);

        // A shop can already carry a differently named snippet set for the locale, for example from a
        // language pack. The BASE set is created next to it, not instead of it.
        $this->snippetSetRepository->create([[
            'name' => 'LanguagePack ' . $this->provisionedLocale,
            'baseFile' => 'messages.' . $this->provisionedLocale,
            'iso' => $this->provisionedLocale,
        ]], $this->context);

        $this->executeOfflineInstall([$this->provisionedLocale])->assertCommandIsSuccessful();
        $this->executeOfflineInstall([$this->provisionedLocale])->assertCommandIsSuccessful();

        static::assertCount(1, $this->findLanguages($this->provisionedLocale));
        static::assertCount(1, $this->findSnippetSets($this->provisionedLocale, 'BASE ' . $this->provisionedLocale));
        static::assertCount(2, $this->findSnippetSets($this->provisionedLocale));
    }

    public function testOfflineInstallRecreatesADeletedSnippetSet(): void
    {
        $this->provideTranslationFile($this->provisionedLocale);

        $this->executeOfflineInstall([$this->provisionedLocale])->assertCommandIsSuccessful();

        $snippetSet = $this->findSnippetSets($this->provisionedLocale, 'BASE ' . $this->provisionedLocale)->first();
        static::assertNotNull($snippetSet);
        $this->snippetSetRepository->delete([['id' => $snippetSet->getId()]], $this->context);

        $this->executeOfflineInstall([$this->provisionedLocale])->assertCommandIsSuccessful();

        static::assertCount(1, $this->findLanguages($this->provisionedLocale));
        static::assertCount(1, $this->findSnippetSets($this->provisionedLocale, 'BASE ' . $this->provisionedLocale));
    }

    public function testOfflineInstallFailsWhenTheLocaleDirectoryHoldsNoFile(): void
    {
        // The bare directory is what a download that fetched no file at all leaves behind
        $this->translationFilesystem->createDirectory(
            Path::join($this->translationLoader->getLocalePath($this->unprovisionedLocale), 'Platform')
        );

        $this->expectExceptionObject(SnippetException::translationsUnavailable([$this->unprovisionedLocale]));

        try {
            $this->executeOfflineInstall([$this->unprovisionedLocale]);
        } finally {
            static::assertCount(0, $this->findLanguages($this->unprovisionedLocale));
            static::assertCount(0, $this->findSnippetSets($this->unprovisionedLocale));
        }
    }

    public function testOfflineInstallLinksNothingWhenOneRequestedLocaleHasNoFiles(): void
    {
        $this->provideTranslationFile($this->provisionedLocale);

        $this->expectExceptionObject(SnippetException::translationsUnavailable([$this->unprovisionedLocale]));

        try {
            $this->executeOfflineInstall([$this->unprovisionedLocale, $this->provisionedLocale]);
        } finally {
            // Every locale is checked before the first one is linked, so the locale that does have
            // files is not installed either and no half-installed state is left behind.
            static::assertCount(0, $this->findLanguages($this->provisionedLocale));
            static::assertCount(0, $this->findLanguages($this->unprovisionedLocale));
        }
    }

    /**
     * @param list<string> $locales
     */
    private function executeOfflineInstall(array $locales, bool $skipActivation = false): CommandTester
    {
        $command = new InstallTranslationCommand(
            $this->translationLoader,
            $this->config,
            static::getContainer()->get(TranslationMetadataStore::class),
        );

        $options = ['--locales' => implode(',', $locales), '--offline' => true];

        if ($skipActivation) {
            $options['--skip-activation'] = true;
        }

        $tester = new CommandTester($command);
        $tester->execute($options);

        return $tester;
    }

    private function provideTranslationFile(string $locale): void
    {
        $this->translationFilesystem->write(
            Path::join($this->translationLoader->getLocalePath($locale), 'Platform', 'messages.' . $locale . '.base.json'),
            '{}'
        );
    }

    /**
     * Two configured languages this installation has not installed: the locale must already exist so
     * the command does not take the pseudo-locale path, and no language may point at it yet, so the
     * tests can assert on absolute counts.
     *
     * @return array{0: Language, 1: Language}
     */
    private function findInstallableLanguages(): array
    {
        $found = [];

        foreach ($this->config->languages as $language) {
            if ($this->findLocaleId($language->locale) === null) {
                continue;
            }

            if ($this->findLanguages($language->locale)->count() > 0) {
                continue;
            }

            $found[] = $language;

            if (\count($found) === 2) {
                return [$found[0], $found[1]];
            }
        }

        static::fail('This installation has no two configured locales left without a language');
    }

    private function findLocaleId(string $locale): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', $locale));
        $criteria->setLimit(1);

        return $this->localeRepository->searchIds($criteria, $this->context)->firstId();
    }

    private function findLanguages(string $locale): LanguageCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', $locale));

        return $this->languageRepository->search($criteria, $this->context)->getEntities();
    }

    private function findSnippetSets(string $locale, ?string $name = null): SnippetSetCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $locale));

        if ($name !== null) {
            $criteria->addFilter(new EqualsFilter('name', $name));
        }

        return $this->snippetSetRepository->search($criteria, $this->context)->getEntities();
    }
}
