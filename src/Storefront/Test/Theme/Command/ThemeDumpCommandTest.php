<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Command\ThemeDumpCommand;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Symfony\Component\Console\Tester\CommandTester;

class ThemeDumpCommandTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private $themeId;

    public function testExecuteShouldResolveThemeInheritanceChainAndConsiderThemeIdArgument(): void
    {
        $this->setUpExampleThemes();

        $themeFileResolverMock = new ThemeFileResolverMock();
        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            $this->getContainer()->get('theme.repository'),
            $this->getContainer()->getParameter('kernel.project_dir')
        );

        $commandTester = new CommandTester($themeDumpCommand);

        $commandTester->execute([
            'theme-id' => $this->themeId,
        ]);

        static::assertSame('expectedConfig', $themeFileResolverMock->themeConfig->getThemeConfig()[0]);
    }

    private function getPluginRegistryMock(): MockObject
    {
        $storePluginConfiguration1 = new StorefrontPluginConfiguration();
        $storePluginConfiguration1->setTechnicalName('parentTheme');
        $storePluginConfiguration1->setThemeConfig([
            'expectedConfig',
        ]);
        $storePluginConfiguration1->setBasePath('');

        $storePluginConfiguration2 = new StorefrontPluginConfiguration();
        $storePluginConfiguration2->setTechnicalName('childTheme');
        $storePluginConfiguration2->setThemeConfig([
            'unexpectedConfig',
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

    private function setUpExampleThemes(): void
    {
        $themeRepository = $this->getContainer()->get('theme.repository');
        $themeSalesChannelRepository = $this->getContainer()->get('theme_sales_channel.repository');
        $context = Context::createDefaultContext();

        $parentThemeId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->themeId = $childId;

        $themes = [
            $parentThemeId => Uuid::randomHex(),
            $childId => Uuid::randomHex(),
        ];

        $themeRepository->create(
            [
                [
                    'id' => $parentThemeId,
                    'name' => 'Parent theme',
                    'technicalName' => 'parentTheme',
                    'author' => 'test',
                    'active' => true,
                ],
                [
                    'id' => $childId,
                    'parentThemeId' => $parentThemeId,
                    'name' => 'Child theme',
                    'author' => 'test',
                    'active' => true,
                ],
            ],
            $context
        );

        foreach ($themes as $themeId => $salesChannelId) {
            $this->createSalesChannel([
                'id' => $salesChannelId, 'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/' . $themeId,
                    ],
                ],
            ]);

            $themeSalesChannelRepository->create([['themeId' => $themeId, 'salesChannelId' => $salesChannelId]], $context);
        }
    }
}

class ThemeFileResolverMock extends ThemeFileResolver
{
    public $themeConfig;

    public function __construct()
    {
    }

    public function resolveFiles(
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles
    ): array {
        $this->themeConfig = $themeConfig;

        return [];
    }
}
