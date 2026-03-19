<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Exception\InvalidPlatformVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\Service\AppConfigReader;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\CompilerConfiguration;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ThemeCompiler::class)]
class ThemeCompilerPluginConfigurationTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');
    }

    // ===================================
    // Plugin Configuration Integration Tests
    // ===================================

    public function testCompilesWithPluginScssVariables(): void
    {
        $testScss = <<<'SCSS'
.test-selector-plugin {
    background: $simple-plugin-backgroundcolor;
    color: $simple-plugin-fontcolor;
}
SCSS;

        $configService = $this->getConfigurationService([
            new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
        ]);

        $storefrontPluginRegistry = $this->getStorefrontPluginRegistry([
            new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
        ]);

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configService, $storefrontPluginRegistry);
        $this->eventDispatcher->addSubscriber($subscriber);

        $sysConfigService = static::getContainer()->get(SystemConfigService::class);
        $sysConfigService->set('SimplePlugin.config.simplePluginBackgroundcolor', '#ffffff');
        $sysConfigService->set('SimplePlugin.config.simplePluginFontcolor', '#000000');

        try {
            $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
                new CompilerConfiguration([]),
                '$simple-plugin-backgroundcolor: #ffffff; $simple-plugin-fontcolor: #000000; ' . $testScss
            );

            static::assertStringContainsString('.test-selector-plugin', $result);
            static::assertStringContainsString('#ffffff', $result);
            static::assertStringContainsString('#000000', $result);
        } finally {
            $this->eventDispatcher->removeSubscriber($subscriber);
        }
    }

    public function testCompilesWithAppScssVariables(): void
    {
        $testScss = <<<'SCSS'
.test-selector-app {
    background: $no-theme-custom-css-backgroundcolor;
    color: $no-theme-custom-css-fontcolor;
}
SCSS;

        $configService = $this->getConfigurationService([
            new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
        ]);

        $storefrontPluginRegistry = $this->getStorefrontPluginRegistry([
            new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
        ]);

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configService, $storefrontPluginRegistry);
        $this->eventDispatcher->addSubscriber($subscriber);

        $sysConfigService = static::getContainer()->get(SystemConfigService::class);
        $sysConfigService->set('SwagNoThemeCustomCss.config.noThemeCustomCssBackGroundcolor', '#aabbcc');
        $sysConfigService->set('SwagNoThemeCustomCss.config.noThemeCustomCssFontcolor', '#ddeeff');

        try {
            $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
                new CompilerConfiguration([]),
                '$no-theme-custom-css-backgroundcolor: #aabbcc; $no-theme-custom-css-fontcolor: #ddeeff; ' . $testScss
            );

            static::assertStringContainsString('.test-selector-app', $result);
            static::assertStringContainsString('#aabbcc', $result);
            static::assertStringContainsString('#ddeeff', $result);
        } finally {
            $this->eventDispatcher->removeSubscriber($subscriber);
        }
    }

    public function testCompilesPluginAndAppCssWithNullValueHandling(): void
    {
        $testScss = <<<'SCSS'
.test-selector-plugin {
    background: $simple-plugin-backgroundcolor;
    color: $simple-plugin-fontcolor;
    border: $simple-plugin-bordercolor;
}
.test-selector-app {
    background: $no-theme-custom-css-backgroundcolor;
    color: $no-theme-custom-css-fontcolor;
    border: $no-theme-custom-css-bordercolor;
}
SCSS;

        $configService = $this->getConfigurationService([
            new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
        ]);

        $storefrontPluginRegistry = $this->getStorefrontPluginRegistry([
            new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
        ]);

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configService, $storefrontPluginRegistry);
        $this->eventDispatcher->addSubscriber($subscriber);

        $sysConfigService = static::getContainer()->get(SystemConfigService::class);
        $sysConfigService->set('SimplePlugin.config.simplePluginBackgroundcolor', '#fff');
        $sysConfigService->set('SwagNoThemeCustomCss.config.noThemeCustomCssBackGroundcolor', '#aaa');
        // Note: bordercolor and fontcolor are intentionally NOT set to test null value handling

        try {
            // Build variables with nulls
            $variables = '$simple-plugin-backgroundcolor: #fff; ';
            $variables .= '$simple-plugin-fontcolor: #eee; ';
            $variables .= '$simple-plugin-bordercolor: null; ';
            $variables .= '$no-theme-custom-css-backgroundcolor: #aaa; ';
            $variables .= '$no-theme-custom-css-fontcolor: #eee; ';
            $variables .= '$no-theme-custom-css-bordercolor: null; ';

            $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
                new CompilerConfiguration([]),
                $variables . $testScss
            );

            // Verify plugin selector with values
            static::assertStringContainsString('.test-selector-plugin', $result);
            static::assertStringContainsString('background:#fff', str_replace(' ', '', $result));
            static::assertStringContainsString('color:#eee', str_replace(' ', '', $result));

            // Verify app selector with values
            static::assertStringContainsString('.test-selector-app', $result);
            static::assertStringContainsString('background:#aaa', str_replace(' ', '', $result));

            // IMPORTANT: Verify that border properties with null values are omitted
            // SCSS omits property definitions when variables have null value
            $normalizedResult = str_replace([' ', "\n", "\r"], '', strtolower($result));
            static::assertStringNotContainsString(
                'border:',
                $normalizedResult,
                'Border properties should be omitted when variable value is null'
            );
        } finally {
            $this->eventDispatcher->removeSubscriber($subscriber);
        }
    }

    // ===================================
    // Database Resilience Tests
    // ===================================

    public function testHandlesDatabaseException(): void
    {
        $configService = $this->getConfigurationServiceDbException([
            new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
        ]);

        $storefrontPluginRegistry = $this->getStorefrontPluginRegistry([
            new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
        ]);

        $event = new ThemeCompilerEnrichScssVariablesEvent(
            [],
            TestDefaults::SALES_CHANNEL,
            Context::createDefaultContext()
        );

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configService, $storefrontPluginRegistry);

        // Should not throw exception
        $subscriber->enrichExtensionVars($event);

        // No variables should be added when a DB exception occurs
        static::assertEmpty($event->getVariables());
    }

    // ===================================
    // Helper Methods
    // ===================================

    /**
     * @param array<int, Plugin> $plugins
     */
    private function getConfigurationService(array $plugins): ConfigurationService
    {
        return new ConfigurationService(
            $plugins,
            new ConfigReader(),
            static::getContainer()->get(AppConfigReader::class),
            static::getContainer()->get('app.repository'),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(LoggerInterface::class)
        );
    }

    /**
     * @param array<int, Plugin> $plugins
     */
    private function getConfigurationServiceDbException(array $plugins): ConfigurationService
    {
        return new ConfigurationServiceException(
            $plugins,
            new ConfigReader(),
            static::getContainer()->get(AppConfigReader::class),
            static::getContainer()->get('app.repository'),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(LoggerInterface::class)
        );
    }

    /**
     * @param array<int, Plugin> $plugins
     */
    private function getStorefrontPluginRegistry(array $plugins): StorefrontPluginRegistry
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel
            ->method('getBundles')
            ->willReturn($plugins);

        return new StorefrontPluginRegistry(
            $kernel,
            static::getContainer()->get(StorefrontPluginConfigurationFactory::class),
            static::getContainer()->get(ActiveAppsLoader::class)
        );
    }
}

/**
 * @internal
 */
class ConfigurationServiceException extends ConfigurationService
{
    /**
     * @throws Exception
     */
    public function checkConfiguration(string $domain, Context $context): bool
    {
        throw new InvalidPlatformVersion('any');
    }
}
