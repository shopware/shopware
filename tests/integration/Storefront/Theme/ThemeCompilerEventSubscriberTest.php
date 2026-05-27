<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\MockThemeCompilerConcatenatedSubscriber;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\MockThemeVariablesSubscriber;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class ThemeCompilerEventSubscriberTest extends TestCase
{
    use KernelTestBehaviour;

    private ThemeCompiler $themeCompiler;

    private Filesystem $filesystem;

    private Filesystem $tempFilesystem;

    private EventDispatcherInterface $eventDispatcher;

    private string $mockSalesChannelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->tempFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->mockSalesChannelId = '98432def39fc4624b33213a56b8c944d';
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');

        $this->themeCompiler = new ThemeCompiler(
            $this->filesystem,
            $this->tempFilesystem,
            new CopyBatchInputFactory(),
            static::getContainer()->get(ThemeFileResolver::class),
            true,
            $this->eventDispatcher,
            static::getContainer()->get(ThemeFilesystemResolver::class),
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            static::getContainer()->get(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            new MD5ThemePathBuilder(),
            static::getContainer()->get(ScssPhpCompiler::class),
            [],
            false
        );
    }

    // ===================================
    // Event Subscriber Integration Tests
    // ===================================

    public function testEventSubscriberCanEnrichScssVariables(): void
    {
        $subscriber = new MockThemeVariablesSubscriber(
            static::getContainer()->get(SystemConfigService::class)
        );

        $variables = [
            'sw-color-brand-primary' => '#008490',
        ];

        $event = new ThemeCompilerEnrichScssVariablesEvent(
            $variables,
            $this->mockSalesChannelId,
            Context::createDefaultContext()
        );

        $subscriber->onAddVariables($event);

        $result = $event->getVariables();

        static::assertArrayHasKey('mock-variable-black', $result);
        static::assertSame('#000000', $result['mock-variable-black']);
        static::assertArrayHasKey('mock-variable-special', $result);
        static::assertSame('\'Special value with quotes\'', $result['mock-variable-special']);
    }

    public function testEventSubscriberCanModifyConcatenatedStyles(): void
    {
        $subscriber = new MockThemeCompilerConcatenatedSubscriber();

        $styles = 'body { margin: 0; }';

        $event = new ThemeCompilerConcatenatedStylesEvent($styles, $this->mockSalesChannelId);
        $subscriber->onGetConcatenatedStyles($event);

        $result = $event->getConcatenatedStyles();

        static::assertStringContainsString('body { margin: 0; }', $result);
        static::assertStringContainsString(MockThemeCompilerConcatenatedSubscriber::STYLES_CONCAT, $result);
    }

    public function testVariableEnrichmentEventAffectsCompiledOutput(): void
    {
        // Add a subscriber that enriches variables
        $subscriber = new MockThemeVariablesSubscriber(
            static::getContainer()->get(SystemConfigService::class)
        );
        $this->eventDispatcher->addSubscriber($subscriber);

        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-color-brand-primary' => [
                    'name' => 'sw-color-brand-primary',
                    'type' => 'color',
                    'value' => '#008490',
                ],
            ],
        ]);

        try {
            $this->themeCompiler->compileTheme(
                $this->mockSalesChannelId,
                'test-theme-id',
                $config,
                new StorefrontPluginConfigurationCollection(),
                false,
                Context::createDefaultContext()
            );

            // Check that enriched variables were written
            $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
            static::assertStringContainsString('$mock-variable-black: #000000', $variablesContent);
        } finally {
            $this->eventDispatcher->removeSubscriber($subscriber);
        }
    }
}
