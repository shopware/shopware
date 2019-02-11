<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Composer\IO\NullIO;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class PluginServiceTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour,
        PluginTestsHelper;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        require_once __DIR__ . '/_fixture/SwagTest/SwagTest.php';
        $this->pluginRepo = $this->getContainer()->get('plugin.repository');
        $this->pluginService = $this->createPluginService(
            $this->pluginRepo,
            $this->getContainer()->get('language.repository'),
            $this->getContainer()->getParameter('kernel.project_dir')
        );
        $this->context = Context::createDefaultContext();
    }

    public function testRefreshPlugins(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());
        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepo->search(new Criteria(), $this->context)->first();

        $this->performDefaultTests($plugin);
        self::assertNotNull($plugin->getCreatedAt());
        self::assertNull($plugin->getUpdatedAt());
        self::assertNull($plugin->getUpgradeVersion());
        self::assertNull($plugin->getInstalledAt());
        self::assertNull($plugin->getUpgradedAt());
        self::assertSame('shopware AG', $plugin->getAuthor());
        self::assertSame('(c) by shopware AG', $plugin->getCopyright());
        self::assertSame('MIT', $plugin->getLicense());
        self::assertSame('English description', $plugin->getDescription());
        self::assertSame('https://www.test.com/', $plugin->getManufacturerLink());
        self::assertSame('https://www.test.com/support', $plugin->getSupportLink());
        self::assertSame($this->getValidEnglishChangelog(), $plugin->getChangelog());
    }

    public function testRefreshPluginsWithGermanContext(): void
    {
        $context = new Context(new SourceContext(), [Defaults::CATALOG], [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM_DE]);

        $this->pluginService->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepo->search(new Criteria(), $context)->first();

        $this->performDefaultGermanTests($plugin);
        self::assertNotNull($plugin->getCreatedAt());
        self::assertNull($plugin->getUpdatedAt());
        self::assertNull($plugin->getUpgradeVersion());
        self::assertNull($plugin->getInstalledAt());
        self::assertNull($plugin->getUpgradedAt());
        self::assertSame('shopware AG', $plugin->getAuthor());
        self::assertSame('(c) by shopware AG', $plugin->getCopyright());
        self::assertSame('MIT', $plugin->getLicense());
        self::assertSame('Deutsche Beschreibung', $plugin->getDescription());
        self::assertSame('https://www.test.de/', $plugin->getManufacturerLink());
        self::assertSame('https://www.test.de/support', $plugin->getSupportLink());
        self::assertSame($this->getValidGermanChangelog(), $plugin->getChangelog());
    }

    public function testRefreshPluginsExistingWithPluginUpdate(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context, \SwagTest\SwagTest::PLUGIN_OLD_VERSION);

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepo->search(new Criteria(), $this->context)->first();

        self::assertSame(\SwagTest\SwagTest::PLUGIN_NAME, $plugin->getName());
        self::assertSame(\SwagTest\SwagTest::PLUGIN_LABEL, $plugin->getLabel());
        self::assertSame(\SwagTest\SwagTest::PLUGIN_VERSION, $plugin->getUpgradeVersion());
    }

    public function testRefreshPluginsExistingWithoutPluginUpdate(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context);

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepo->search(new Criteria(), $this->context)->first();

        $this->performDefaultTests($plugin);
        self::assertNull($plugin->getUpgradeVersion());
    }

    public function testRefreshPluginsDeleteNonExistingPlugin(): void
    {
        $this->pluginRepo->create(
            [
                [
                    'name' => 'SwagFoo',
                    'version' => '1.1.1',
                    'label' => 'Foo Label',
                ],
            ],
            $this->context
        );

        $this->pluginService->refreshPlugins($this->context, new NullIO());
        $pluginCollection = $this->pluginRepo->search(new Criteria(), $this->context)->getEntities();
        self::assertCount(1, $pluginCollection);
        /** @var PluginEntity $plugin */
        $plugin = $pluginCollection->first();

        $this->performDefaultTests($plugin);
        self::assertNull($plugin->getUpgradeVersion());
    }

    public function testGetPluginByName(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context);

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->performDefaultTests($plugin);
    }

    public function testGetPluginByNameThrowsException(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context);

        $this->expectException(PluginNotFoundException::class);
        $this->expectExceptionMessage('Plugin by name "SwagFoo" not found');
        $this->pluginService->getPluginByName('SwagFoo', $this->context);
    }

    private function performDefaultTests(PluginEntity $plugin): void
    {
        self::assertSame(\SwagTest\SwagTest::PLUGIN_NAME, $plugin->getName());
        self::assertSame(\SwagTest\SwagTest::PLUGIN_LABEL, $plugin->getLabel());
        self::assertSame(\SwagTest\SwagTest::PLUGIN_VERSION, $plugin->getVersion());
    }

    private function performDefaultGermanTests(PluginEntity $plugin)
    {
        self::assertSame(\SwagTest\SwagTest::PLUGIN_NAME, $plugin->getName());
        self::assertSame(\SwagTest\SwagTest::PLUGIN_GERMAN_LABEL, $plugin->getLabel());
        self::assertSame(\SwagTest\SwagTest::PLUGIN_VERSION, $plugin->getVersion());
    }

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
}
