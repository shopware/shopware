<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Theme\Command\ThemeChangeCommand;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class ThemeChangeCommandTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private EntityRepository $salesChannelRepository;

    private MockObject $pluginRegistry;

    private EntityRepository $themeRepository;

    protected function setUp(): void
    {
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->themeRepository = $this->getContainer()->get('theme.repository');
    }

    public function testThemeChangeCommandAllSalesChannels(): void
    {
        $context = Context::createDefaultContext();

        $salesChannels = $this->getSalesChannelData();
        $themes = $this->getThemeData();

        foreach ($salesChannels as $salesChannel) {
            $this->createSalesChannel($salesChannel);
        }

        $this->themeRepository->create($themes, $context);

        $this->pluginRegistry = $this->getPluginRegistryMock();
        $salesChannels = $this->salesChannelRepository->search(
            new Criteria(),
            Context::createDefaultContext()
        )->getEntities();

        $arguments = [];

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $arguments[] = [
                $themes[0]['id'],
                $salesChannel->getId(),
                Context::createDefaultContext(),
            ];
        }

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects(static::exactly(\count($salesChannels)))
            ->method('assignTheme');

        $themeChangeCommand = new ThemeChangeCommand(
            $themeService,
            $this->pluginRegistry,
            $this->salesChannelRepository,
            $this->themeRepository
        );

        $commandTester = new CommandTester($themeChangeCommand);
        $application = new Application();
        $application->add($themeChangeCommand);

        $commandTester->execute([
            'theme-name' => $themes[0]['technicalName'],
            '--all' => true,
        ]);
    }

    public function testThemeChangeCommandWithOneSalesChannel(): void
    {
        $context = Context::createDefaultContext();

        $salesChannel = $this->getSalesChannelData()[0];
        $themes = $this->getThemeData();

        $this->createSalesChannel($salesChannel);

        $this->themeRepository->create($themes, $context);

        $this->pluginRegistry = $this->getPluginRegistryMock();

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects(static::exactly(1))
            ->method('assignTheme')
            ->with($themes[0]['id'], $salesChannel['id'], $context);

        $themeChangeCommand = new ThemeChangeCommand(
            $themeService,
            $this->pluginRegistry,
            $this->salesChannelRepository,
            $this->themeRepository
        );

        $commandTester = new CommandTester($themeChangeCommand);
        $application = new Application();
        $application->add($themeChangeCommand);

        $commandTester->execute([
            'theme-name' => $themes[0]['technicalName'],
            '--sales-channel' => $salesChannel['id'],
        ]);
    }

    public function testThemeChangeCommandWithNotExistingSalesChannelAndTheme(): void
    {
        $themeService = $this->createMock(ThemeService::class);
        $this->pluginRegistry = $this->getPluginRegistryMock();

        $themeChangeCommand = new ThemeChangeCommand(
            $themeService,
            $this->pluginRegistry,
            $this->salesChannelRepository,
            $this->themeRepository
        );

        $commandTester = new CommandTester($themeChangeCommand);
        $application = new Application();
        $application->add($themeChangeCommand);

        $commandTester->execute(['theme-name' => 'not existing theme', '--sales-channel' => 'not existing saleschannel'], ['interactive' => true]);

        static::assertStringContainsString('[ERROR] Could not find sales channel with ID not existing saleschannel', $commandTester->getDisplay());
    }

    public function testThemeChangeCommandWithNoSalesChannel(): void
    {
        $themeService = $this->createMock(ThemeService::class);
        $this->pluginRegistry = $this->getPluginRegistryMock();

        $themeChangeCommand = new ThemeChangeCommand(
            $themeService,
            $this->pluginRegistry,
            $this->salesChannelRepository,
            $this->themeRepository
        );

        $commandTester = new CommandTester($themeChangeCommand);
        $application = new Application();
        $application->add($themeChangeCommand);

        $commandTester->execute(['--all' => true, '--sales-channel' => 'foo'], ['interactive' => true]);

        static::assertStringContainsString('[ERROR] You can use either --sales-channel or --all, not both at the same time.', $commandTester->getDisplay());
    }

    public function testThemeChangeCommandWithOneSalesChannelWithoutCompiling(): void
    {
        $context = Context::createDefaultContext();

        $salesChannel = $this->getSalesChannelData()[0];
        $themes = $this->getThemeData();

        $this->createSalesChannel($salesChannel);

        $this->themeRepository->create($themes, $context);

        $this->pluginRegistry = $this->getPluginRegistryMock();

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects(static::exactly(1))
            ->method('assignTheme')
            ->with($themes[0]['id'], $salesChannel['id'], $context, true);

        $themeChangeCommand = new ThemeChangeCommand(
            $themeService,
            $this->pluginRegistry,
            $this->salesChannelRepository,
            $this->themeRepository
        );

        $commandTester = new CommandTester($themeChangeCommand);
        $application = new Application();
        $application->add($themeChangeCommand);

        $commandTester->execute([
            'theme-name' => $themes[0]['technicalName'],
            '--sales-channel' => $salesChannel['id'],
            '--no-compile' => true,
        ]);
    }

    public function testThemeChangeCommandSync(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(ThemeService::STATE_NO_QUEUE);

        $salesChannel = $this->getSalesChannelData()[0];
        $themes = $this->getThemeData();

        $this->createSalesChannel($salesChannel);

        $this->themeRepository->create($themes, $context);

        $this->pluginRegistry = $this->getPluginRegistryMock();

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects(static::exactly(1))
            ->method('assignTheme')
            ->with($themes[0]['id'], $salesChannel['id'], $context, false);

        $themeChangeCommand = new ThemeChangeCommand(
            $themeService,
            $this->pluginRegistry,
            $this->salesChannelRepository,
            $this->themeRepository
        );

        $commandTester = new CommandTester($themeChangeCommand);
        $application = new Application();
        $application->add($themeChangeCommand);

        $commandTester->execute([
            'theme-name' => $themes[0]['technicalName'],
            '--sales-channel' => $salesChannel['id'],
            '--sync' => true,
        ]);
    }

    private function getPluginRegistryMock(): MockObject&StorefrontPluginRegistry
    {
        $storePluginConfiguration1 = new StorefrontPluginConfiguration('parentTheme');
        $storePluginConfiguration1->setThemeConfig([
            'any' => 'expectedConfig',
        ]);
        $storePluginConfiguration1->setBasePath('');

        $storePluginConfiguration2 = new StorefrontPluginConfiguration('childTheme');
        $storePluginConfiguration2->setThemeConfig([
            'any' => 'unexpectedConfig',
        ]);
        $storePluginConfiguration2->setBasePath('');

        $mock = $this->getMockBuilder(StorefrontPluginRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getConfigurations')
            ->willReturn(
                new StorefrontPluginConfigurationCollection([$storePluginConfiguration1, $storePluginConfiguration2])
            );

        return $mock;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSalesChannelData(): array
    {
        return [
            [
                'id' => Uuid::randomHex(),
                'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/salesChannel1',
                    ],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/salesChannel2',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getThemeData(): array
    {
        return [
            [
                'id' => Uuid::randomHex(),
                'name' => 'Theme1',
                'technicalName' => 'theme_1',
                'author' => 'test',
                'active' => true,
            ],
            [
                'id' => Uuid::randomHex(),
                'name' => 'Theme2',
                'technicalName' => 'theme_2',
                'author' => 'test',
                'active' => true,
            ],
        ];
    }
}
