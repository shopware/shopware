<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\Test\Stub\Doctrine\TestExceptionFactory;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeCompilerEnrichScssVarSubscriber::class)]
class ThemeCompilerEnrichScssVarSubscriberTest extends TestCase
{
    private ConfigurationService&Stub $configService;

    private StorefrontPluginRegistry&Stub $storefrontPluginRegistry;

    protected function setUp(): void
    {
        $this->configService = static::createStub(ConfigurationService::class);
        $this->storefrontPluginRegistry = static::createStub(StorefrontPluginRegistry::class);
    }

    public function testEnrichExtensionVarsReturnsNothingWithNoStorefrontPlugin(): void
    {
        $configService = $this->createMock(ConfigurationService::class);
        $configService->expects($this->never())->method('getResolvedConfiguration');

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configService, $this->storefrontPluginRegistry);

        $subscriber->enrichExtensionVars(
            new ThemeCompilerEnrichScssVariablesEvent(
                [],
                TestDefaults::SALES_CHANNEL,
                Context::createDefaultContext()
            )
        );
    }

    public function testOnlyDBExceptionIsSilenced(): void
    {
        $exception = new \InvalidArgumentException();
        $this->configService->method('getResolvedConfiguration')->willThrowException($exception);
        $this->storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->configService, $this->storefrontPluginRegistry);
        $this->expectExceptionObject($exception);

        $subscriber->enrichExtensionVars(
            new ThemeCompilerEnrichScssVariablesEvent(
                [],
                TestDefaults::SALES_CHANNEL,
                Context::createDefaultContext()
            )
        );
    }

    public function testDBException(): void
    {
        $this->configService->method('getResolvedConfiguration')->willThrowException(TestExceptionFactory::createException('test'));
        $this->storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->configService, $this->storefrontPluginRegistry);

        $exception = null;
        try {
            $subscriber->enrichExtensionVars(
                new ThemeCompilerEnrichScssVariablesEvent(
                    [],
                    TestDefaults::SALES_CHANNEL,
                    Context::createDefaultContext()
                )
            );
        } catch (DBALException $exception) {
        }

        static::assertNull($exception);
    }

    /**
     * EnrichScssVarSubscriber doesn't throw an exception if we have corrupted element values.
     * This can happen on updates from older version when the values in the administration where not checked before save
     */
    public function testOutputsPluginCssCorrupt(): void
    {
        $this->configService->method('getResolvedConfiguration')->willReturn([
            'card' => [
                'elements' => [
                    new \DateTime(),
                ],
            ],
        ]);

        $this->storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->configService, $this->storefrontPluginRegistry);

        $event = new ThemeCompilerEnrichScssVariablesEvent(
            ['bla' => 'any'],
            TestDefaults::SALES_CHANNEL,
            Context::createDefaultContext()
        );

        $backupEvent = clone $event;

        $subscriber->enrichExtensionVars(
            $event
        );

        static::assertEquals($backupEvent, $event);
    }

    public function testGetSubscribedEventsReturnsOnlyOneTypeOfEvent(): void
    {
        static::assertSame(
            [
                ThemeCompilerEnrichScssVariablesEvent::class => 'enrichExtensionVars',
            ],
            ThemeCompilerEnrichScssVarSubscriber::getSubscribedEvents()
        );
    }
}
