<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Composer\IO\NullIO;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\ExceptionCollection;
use Shopware\Core\Framework\Plugin\Exception\PluginChangelogInvalidException;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use SwagTest\SwagTest;
use SwagTestNoDefaultLang\SwagTestNoDefaultLang;

/**
 * @internal
 *
 * @group slow
 * @group skip-paratest
 */
class PluginServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PluginTestsHelper;

    /**
     * @var EntityRepository
     */
    private $pluginRepo;

    private PluginService $pluginService;

    private Context $context;

    private string $iso = 'nl-NL';

    protected function setUp(): void
    {
        require_once __DIR__ . '/_fixture/plugins/SwagTest/src/SwagTest.php';
        require_once __DIR__ . '/_fixture/plugins/SwagTestNoDefaultLang/src/SwagTestNoDefaultLang.php';
        $this->pluginRepo = $this->getContainer()->get('plugin.repository');
        $this->pluginService = $this->createPluginService(
            __DIR__ . '/_fixture/plugins',
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->pluginRepo,
            $this->getContainer()->get('language.repository'),
            $this->getContainer()->get(PluginFinder::class)
        );
        $this->context = Context::createDefaultContext();
    }

    public function testRefreshPlugins(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->fetchSwagTestPluginEntity();

        $this->assertDefaultPlugin($plugin);
        $this->assertPluginMetaInformation($plugin);
        static::assertSame('English description', $plugin->getDescription());
        static::assertSame('https://www.test.com/', $plugin->getManufacturerLink());
        static::assertSame('https://www.test.com/support', $plugin->getSupportLink());
        static::assertSame($this->getValidEnglishChangelog(), $plugin->getChangelog());
    }

    public function testRefreshPluginWithoutExtraLabelProperty(): void
    {
        $errors = $this->pluginService->refreshPlugins($this->context, new NullIO());

        static::assertInstanceOf(ExceptionCollection::class, $errors);
        static::assertTrue($errors->count() > 0);

        $composerJsonException = $errors->filter(fn (ShopwareHttpException $error) => $error instanceof PluginComposerJsonInvalidException);

        static::assertNotEmpty($composerJsonException);

        $errorFound = false;
        $errorString = 'Plugin composer.json has invalid "type" (must be "shopware-platform-plugin"), or invalid "extra/shopware-plugin-class" value, or missing extra.label property';

        foreach ($composerJsonException->getIterator() as $exception) {
            if (empty($exception->getParameters()['composerJsonPath']) || $exception->getParameters()['composerJsonPath'] !== __DIR__ . '/_fixture/plugins/SwagTestNoExtraLabelProperty/composer.json') {
                continue;
            }

            if (!empty($exception->getParameters()['errorsString']) && $exception->getParameters()['errorsString'] === $errorString) {
                $errorFound = true;
            }
        }

        static::assertTrue($errorFound);
    }

    public function testRefreshPluginsWithNonStandardLanguage(): void
    {
        $nonStandardLanguageContext = $this->createNonStandardLanguageContext($this->iso);

        $this->pluginService->refreshPlugins($nonStandardLanguageContext, new NullIO());

        $plugin = $this->fetchSwagTestPluginEntity($nonStandardLanguageContext);

        $this->assertDefaultPlugin($plugin);
        $this->assertPluginMetaInformation($plugin);
        static::assertSame('English description', $plugin->getTranslated()['description']);
        static::assertSame('https://www.test.com/', $plugin->getTranslated()['manufacturerLink']);
        static::assertSame('https://www.test.com/support', $plugin->getTranslated()['supportLink']);
        static::assertSame($this->getValidDutchChangelog(), $plugin->getChangelog());
    }

    public function testRefreshPluginsWithDifferentDefaultLanguage(): void
    {
        $this->setNewSystemLanguage($this->iso);

        $this->pluginService->refreshPlugins(Context::createDefaultContext(), new NullIO());

        $plugin = $this->fetchSwagTestNoDefaultLangPluginEntity();

        $this->assertNoDefaultPlugin($plugin);
        $this->assertPluginMetaInformation($plugin);
        static::assertSame('Dutch Beschrijving', $plugin->getTranslated()['description']);
        static::assertSame('https://www.test.nl/', $plugin->getTranslated()['manufacturerLink']);
        static::assertSame('https://www.test.nl/support', $plugin->getTranslated()['supportLink']);
        static::assertSame($this->getValidDutchChangelog(), $plugin->getChangelog());
    }

    public function testRefreshPluginsWithGermanContext(): void
    {
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId()]);

        $this->pluginService->refreshPlugins($context, new NullIO());

        $plugin = $this->fetchSwagTestPluginEntity($context);

        $this->assertPluginMetaInformation($plugin);
        $this->assertGermanPlugin($plugin);
    }

    public function testRefreshPluginsExistingWithPluginUpdate(): void
    {
        $installedAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->createPlugin($this->pluginRepo, $this->context, SwagTest::PLUGIN_OLD_VERSION, $installedAt);

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->fetchSwagTestPluginEntity();

        static::assertSame(SwagTest::class, $plugin->getBaseClass());
        static::assertSame(SwagTest::PLUGIN_LABEL, $plugin->getLabel());
        static::assertSame(SwagTest::PLUGIN_VERSION, $plugin->getUpgradeVersion());
    }

    public function testRefreshPluginsExistingNotInstalledWithPluginUpdate(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context, SwagTest::PLUGIN_OLD_VERSION);

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->fetchSwagTestPluginEntity();

        static::assertSame(SwagTest::class, $plugin->getBaseClass());
        static::assertSame(SwagTest::PLUGIN_LABEL, $plugin->getLabel());
        static::assertSame(SwagTest::PLUGIN_VERSION, $plugin->getVersion());
    }

    public function testRefreshPluginsExistingWithoutPluginUpdate(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context);

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->fetchSwagTestPluginEntity();

        $this->assertDefaultPlugin($plugin);
        static::assertNull($plugin->getUpgradeVersion());
    }

    public function testRefreshPluginsDeleteNonExistingPlugin(): void
    {
        $nonExistentPluginBaseClass = 'SwagFoo\\SwagFoo';
        $this->pluginRepo->create(
            [
                [
                    'name' => 'SwagFoo',
                    'baseClass' => $nonExistentPluginBaseClass,
                    'version' => '1.1.1',
                    'label' => 'Foo Label',
                    'autoload' => [],
                ],
            ],
            $this->context
        );

        $pluginCollectionBefore = $this->pluginRepo->search(new Criteria(), $this->context)->getEntities();
        static::assertInstanceOf(PluginEntity::class, $pluginCollectionBefore->filterByProperty('baseClass', $nonExistentPluginBaseClass)->first());

        $this->pluginService->refreshPlugins($this->context, new NullIO());
        $pluginCollection = $this->pluginRepo->search(new Criteria(), $this->context)->getEntities();

        static::assertNull($pluginCollection->filterByProperty('baseClass', $nonExistentPluginBaseClass)->first());
        /** @var PluginEntity $plugin */
        $plugin = $pluginCollection->filterByProperty('baseClass', SwagTest::class)->first();

        $this->assertDefaultPlugin($plugin);
        static::assertNull($plugin->getUpgradeVersion());
    }

    public function testRefreshWithPluginErrors(): void
    {
        $errors = $this->pluginService->refreshPlugins($this->context, new NullIO());
        static::assertNotEmpty($errors);

        $changeLogErrors = $errors->filter(fn ($error) => $error instanceof PluginChangelogInvalidException);

        static::assertCount(1, $changeLogErrors);

        $changeLogError = $changeLogErrors->first();

        static::assertNotNull($changeLogError);
        static::assertStringContainsString(
            'Framework/Test/Plugin/_fixture/plugins/SwagTestErrors/CHANGELOG.md" is invalid.',
            $changeLogError->getMessage()
        );
    }

    public function testGetPluginByName(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context);

        $plugin = $this->pluginService->getPluginByName('SwagTest', $this->context);

        $this->assertDefaultPlugin($plugin);
    }

    public function testGetPluginByNameThrowsException(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context);

        $this->expectException(PluginNotFoundException::class);
        $this->expectExceptionMessage('Plugin by name "SwagFoo" not found');
        $this->pluginService->getPluginByName('SwagFoo', $this->context);
    }

    private function assertDefaultPlugin(PluginEntity $plugin): void
    {
        static::assertSame(SwagTest::class, $plugin->getBaseClass());
        static::assertSame(SwagTest::PLUGIN_LABEL, $plugin->getTranslated()['label']);
        static::assertSame(SwagTest::PLUGIN_VERSION, $plugin->getVersion());
    }

    private function assertNoDefaultPlugin(PluginEntity $plugin): void
    {
        static::assertSame(SwagTestNoDefaultLang::class, $plugin->getBaseClass());
        static::assertSame(SwagTestNoDefaultLang::PLUGIN_LABEL, $plugin->getTranslated()['label']);
        static::assertSame(SwagTestNoDefaultLang::PLUGIN_VERSION, $plugin->getVersion());
    }

    private function assertGermanPlugin(PluginEntity $plugin): void
    {
        static::assertSame(SwagTest::class, $plugin->getBaseClass());
        static::assertSame(SwagTest::PLUGIN_GERMAN_LABEL, $plugin->getLabel());
        static::assertSame(SwagTest::PLUGIN_VERSION, $plugin->getVersion());
        static::assertSame('Deutsche Beschreibung', $plugin->getDescription());
        static::assertSame('https://www.test.de/', $plugin->getManufacturerLink());
        static::assertSame('https://www.test.de/support', $plugin->getSupportLink());
        static::assertSame($this->getValidGermanChangelog(), $plugin->getChangelog());
    }

    private function assertPluginMetaInformation(PluginEntity $plugin): void
    {
        static::assertNotNull($plugin->getCreatedAt());
        static::assertNull($plugin->getUpdatedAt());
        static::assertNull($plugin->getUpgradeVersion());
        static::assertNull($plugin->getInstalledAt());
        static::assertNull($plugin->getUpgradedAt());
        static::assertSame($this->getValidIconAsBase64(), $plugin->getIcon());
        static::assertSame('shopware AG', $plugin->getAuthor());
        static::assertSame('(c) by shopware AG', $plugin->getCopyright());
        static::assertSame('MIT', $plugin->getLicense());
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function getValidEnglishChangelog(): array
    {
        return [
            '1.0.0' => [
                0 => 'initialized SwagTest',
                1 => 'refactored composer.json',
            ],
            '1.0.1' => [
                0 => 'added migrations',
                1 => 'done nothing',
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function getValidGermanChangelog(): array
    {
        return [
            '1.0.0' => [
                0 => 'SwagTest initialisiert',
                1 => 'composer.json angepasst',
            ],
            '1.0.1' => [
                0 => 'Migrationen hinzugefügt',
                1 => 'nichts gemacht',
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function getValidDutchChangelog(): array
    {
        return [
            '1.0.0' => [
                0 => 'SwagTest geïnitialiseerd',
                1 => 'composer.json aangepast',
            ],
            '1.0.1' => [
                0 => 'Migraties toegevoegd',
                1 => 'ongefabriceerde',
            ],
        ];
    }

    private function fetchSwagTestPluginEntity(?Context $context = null): PluginEntity
    {
        if ($context === null) {
            $context = $this->context;
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('baseClass', SwagTest::class));

        return $this->pluginRepo
            ->search($criteria, $context)
            ->first();
    }

    private function fetchSwagTestNoDefaultLangPluginEntity(?Context $context = null): PluginEntity
    {
        if ($context === null) {
            $context = $this->context;
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('baseClass', SwagTestNoDefaultLang::class));

        return $this->pluginRepo
            ->search($criteria, $context)
            ->first();
    }

    private function getValidIconAsBase64(): string
    {
        return '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';
    }

    private function createNonStandardLanguageContext(string $iso): Context
    {
        $id = Uuid::randomHex();

        $languageRepository = $this->getContainer()->get('language.repository');
        $localeId = $this->getIsoId($iso);
        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'name' => $iso,
                    'localeId' => $localeId,
                    'translationCodeId' => $localeId,
                ],
            ],
            Context::createDefaultContext()
        );

        return new Context(new SystemSource(), [], Defaults::CURRENCY, [$id, Defaults::LANGUAGE_SYSTEM]);
    }

    private function setNewSystemLanguage(string $iso): void
    {
        $languageRepository = $this->getContainer()->get('language.repository');

        $localeId = $this->getIsoId($iso);
        $languageRepository->update(
            [
                [
                    'id' => Defaults::LANGUAGE_SYSTEM,
                    'name' => $iso,
                    'localeId' => $localeId,
                    'translationCodeId' => $localeId,
                ],
            ],
            Context::createDefaultContext()
        );
    }

    private function getIsoId(string $iso): string
    {
        /** @var EntityRepository $localeRepository */
        $localeRepository = $this->getContainer()->get('locale.repository');

        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('code', $iso));

        $isoId = $localeRepository->search($criteria, Context::createDefaultContext())->first()->getId();

        return $isoId;
    }
}
