<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Storefront\Theme\Command\ThemeDumpCommand;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigDumper;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class ThemeDumpCommandTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private string $parentThemeId;

    private string $childThemeId;

    private StaticFileConfigDumper&MockObject $staticFileConfigDumperMock;

    protected function tearDown(): void
    {
        static::getContainer()->get(SourceResolver::class)->reset();
    }

    public function testExecuteShouldResolveThemeInheritanceChainAndConsiderThemeIdArgument(): void
    {
        $this->setUpExampleThemes();

        $themeFileResolverMock = new ThemeFileResolverMock();

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->expects($this->once())->method('getFilesystemForStorefrontConfig')->willReturn(new StaticFilesystem());

        $this->staticFileConfigDumperMock = $this->createMock(StaticFileConfigDumper::class);

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            static::getContainer()->get('theme.repository'),
            $this->staticFileConfigDumperMock,
            $themeFilesystemResolver
        );

        $commandTester = new CommandTester($themeDumpCommand);

        $commandTester->execute([
            'theme-id' => $this->childThemeId,
            'domain-url' => 'http://localhost/1/' . $this->childThemeId,
        ]);

        static::assertSame(['any' => 'expectedConfig'], $themeFileResolverMock->themeConfig->getThemeConfig());
    }

    #[DataProvider('getArguments')]
    public function testExecuteShouldSuccess(?string $themeId = null, ?string $domainUrl = null): void
    {
        $this->setUpExampleThemes($themeId);

        $themeFileResolverMock = new ThemeFileResolverMock();
        $themeFilesystemResolverMock = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolverMock->method('getFilesystemForStorefrontConfig')->willReturn(new StaticFilesystem());

        $this->staticFileConfigDumperMock = $this->createMock(StaticFileConfigDumper::class);
        $this->staticFileConfigDumperMock->expects($this->once())->method('dumpConfigInVar');
        $this->staticFileConfigDumperMock->expects($this->once())->method('dumpConfig');

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            static::getContainer()->get('theme.repository'),
            $this->staticFileConfigDumperMock,
            $themeFilesystemResolverMock
        );

        $themeDumpCommand->setHelperSet(new HelperSet([new QuestionHelper()]));
        $commandTester = new CommandTester($themeDumpCommand);

        $userInput = [];

        if (!$themeId) {
            $userInput[] = 'Parent theme';
        }

        if (!$domainUrl) {
            $userInput[] = 'http://localhost/1/' . $this->parentThemeId;
        }

        $commandTester->setInputs($userInput);
        $commandTester->execute([
            'theme-id' => $themeId,
            'domain-url' => $domainUrl,
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testExecuteShouldSuccessWithoutInteraction(): void
    {
        $this->setUpExampleThemes();

        $themeFileResolverMock = new ThemeFileResolverMock();
        $themeFilesystemResolverMock = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolverMock->method('getFilesystemForStorefrontConfig')->willReturn(new StaticFilesystem());

        $this->staticFileConfigDumperMock = $this->createMock(StaticFileConfigDumper::class);

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            static::getContainer()->get('theme.repository'),
            $this->staticFileConfigDumperMock,
            $themeFilesystemResolverMock
        );
        $themeDumpCommand->setHelperSet(new HelperSet([new QuestionHelper()]));

        $commandTester = new CommandTester($themeDumpCommand);
        $commandTester->execute([], ['interactive' => false]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testThemeNameOption(): void
    {
        $this->setUpExampleThemes();

        $themeFileResolverMock = new ThemeFileResolverMock();
        $themeFilesystemResolverMock = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolverMock->method('getFilesystemForStorefrontConfig')->willReturn(new StaticFilesystem());

        $this->staticFileConfigDumperMock = $this->createMock(StaticFileConfigDumper::class);
        $this->staticFileConfigDumperMock->expects($this->once())->method('dumpConfigInVar');
        $this->staticFileConfigDumperMock->expects($this->once())->method('dumpConfig');

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            static::getContainer()->get('theme.repository'),
            $this->staticFileConfigDumperMock,
            $themeFilesystemResolverMock
        );

        // Add HelperSet and execute with non-interactive mode
        $themeDumpCommand->setHelperSet(new HelperSet([new QuestionHelper()]));

        $commandTester = new CommandTester($themeDumpCommand);
        $commandTester->execute(['--theme-name' => 'parentTheme'], ['interactive' => false]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testNoThemeFoundConnectedToStorefront(): void
    {
        // Create a proper EntitySearchResult mock instead of EntityCollection
        $emptyCollection = new EntityCollection();
        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('getEntities')->willReturn($emptyCollection);

        $themeRepository = $this->createMock(EntityRepository::class);
        $themeRepository->method('search')->willReturn($entitySearchResult);

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $this->createMock(ThemeFileResolver::class),
            $themeRepository,
            $this->createMock(StaticFileConfigDumper::class),
            $this->createMock(ThemeFilesystemResolver::class)
        );

        $themeDumpCommand->setHelperSet(new HelperSet([new QuestionHelper()]));

        $commandTester = new CommandTester($themeDumpCommand);
        $exitCode = $commandTester->execute([], ['interactive' => false]);

        static::assertSame(1, $exitCode);
        static::assertStringContainsString('No theme found which is connected to a storefront sales channel', $commandTester->getDisplay());
    }

    public function testNoThemeConfigFound(): void
    {
        $this->setUpExampleThemes();

        $pluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $pluginRegistry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([]));

        $themeDumpCommand = new ThemeDumpCommand(
            $pluginRegistry,
            $this->createMock(ThemeFileResolver::class),
            static::getContainer()->get('theme.repository'),
            $this->createMock(StaticFileConfigDumper::class),
            $this->createMock(ThemeFilesystemResolver::class)
        );

        $themeDumpCommand->setHelperSet(new HelperSet([new QuestionHelper()]));

        $commandTester = new CommandTester($themeDumpCommand);
        $exitCode = $commandTester->execute([], ['interactive' => false]);

        static::assertSame(1, $exitCode);
        static::assertStringContainsString('No theme config found for theme', $commandTester->getDisplay());
    }

    public function testNonExistentThemeId(): void
    {
        $this->setUpExampleThemes();

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $this->createMock(ThemeFileResolver::class),
            static::getContainer()->get('theme.repository'),
            $this->createMock(StaticFileConfigDumper::class),
            $this->createMock(ThemeFilesystemResolver::class)
        );

        $themeDumpCommand->setHelperSet(new HelperSet([new QuestionHelper()]));

        $commandTester = new CommandTester($themeDumpCommand);
        $exitCode = $commandTester->execute(['theme-id' => Uuid::randomHex()], ['interactive' => false]);

        static::assertSame(1, $exitCode);
        static::assertStringContainsString('No theme found which is connected to a storefront sales channel', $commandTester->getDisplay());
    }

    public function testVerifyCorrectDataPassedToDumper(): void
    {
        $this->setUpExampleThemes();
        $domainUrl = 'http://localhost/1/' . $this->parentThemeId;

        $themeFileResolverMock = new ThemeFileResolverMock();
        $themeFilesystemResolverMock = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolverMock->method('getFilesystemForStorefrontConfig')->willReturn(new StaticFilesystem());

        $this->staticFileConfigDumperMock = $this->createMock(StaticFileConfigDumper::class);
        $this->staticFileConfigDumperMock->expects($this->once())
            ->method('dumpConfigInVar')
            ->with(
                'theme-files.json',
                static::callback(function ($dump) use ($domainUrl) {
                    return isset($dump['themeId'])
                        && isset($dump['technicalName'])
                        && $dump['domainUrl'] === $domainUrl;
                })
            );

        $this->staticFileConfigDumperMock->expects($this->once())->method('dumpConfig');

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            static::getContainer()->get('theme.repository'),
            $this->staticFileConfigDumperMock,
            $themeFilesystemResolverMock
        );

        $commandTester = new CommandTester($themeDumpCommand);
        $commandTester->execute([
            'theme-id' => $this->parentThemeId,
            'domain-url' => $domainUrl,
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    /**
     * @return list<array{themeId: string|null, domainUrl: string|null}>
     */
    public static function getArguments(): array
    {
        $themeId = Uuid::randomHex();

        return [
            [
                'themeId' => $themeId,
                'domainUrl' => null,
            ],
            [
                'themeId' => $themeId,
                'domainUrl' => 'http://localhost/1/' . $themeId,
            ],
            [
                'themeId' => null,
                'domainUrl' => 'http://localhost/2/' . $themeId,
            ],
            [
                'themeId' => null,
                'domainUrl' => null,
            ],
        ];
    }

    private function getPluginRegistryMock(): MockObject&StorefrontPluginRegistry
    {
        $storePluginConfiguration1 = new StorefrontPluginConfiguration('parentTheme');
        $storePluginConfiguration1->setThemeConfig([
            'any' => 'expectedConfig',
        ]);

        $storePluginConfiguration2 = new StorefrontPluginConfiguration('childTheme');
        $storePluginConfiguration2->setThemeConfig([
            'any' => 'unexpectedConfig',
        ]);

        $mock = $this->getMockBuilder(StorefrontPluginRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getConfigurations')
            ->willReturn(
                new StorefrontPluginConfigurationCollection([$storePluginConfiguration1, $storePluginConfiguration2])
            );

        return $mock;
    }

    private function setUpExampleThemes(?string $parentThemeId = null): void
    {
        $themeRepository = static::getContainer()->get('theme.repository');
        $themeSalesChannelRepository = static::getContainer()->get('theme_sales_channel.repository');
        $context = Context::createDefaultContext();

        $parentThemeId = $parentThemeId ?? Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->childThemeId = $childId;
        $this->parentThemeId = $parentThemeId;

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
                'id' => $salesChannelId,
                'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/1/' . $themeId,
                    ],
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/2/' . $themeId,
                    ],
                ],
            ]);

            $themeSalesChannelRepository->create([['themeId' => $themeId, 'salesChannelId' => $salesChannelId]], $context);
        }
    }
}

/**
 * @internal
 */
class ThemeFileResolverMock extends ThemeFileResolver
{
    public StorefrontPluginConfiguration $themeConfig;

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
