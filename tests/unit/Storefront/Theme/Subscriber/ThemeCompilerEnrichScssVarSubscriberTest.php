<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigCard;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigTab;
use Shopware\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
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
    private SystemConfigDefinitionService&Stub $systemConfigDefinitionService;

    private StorefrontPluginRegistry&Stub $storefrontPluginRegistry;

    protected function setUp(): void
    {
        $this->systemConfigDefinitionService = static::createStub(SystemConfigDefinitionService::class);
        $this->storefrontPluginRegistry = static::createStub(StorefrontPluginRegistry::class);
    }

    public function testEnrichExtensionVarsReturnsNothingWithNoStorefrontPlugin(): void
    {
        $systemConfigDefinitionService = $this->createMock(SystemConfigDefinitionService::class);
        $systemConfigDefinitionService->expects($this->never())->method('getResolvedConfiguration');

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($systemConfigDefinitionService, $this->storefrontPluginRegistry);

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
        $this->systemConfigDefinitionService->method('getResolvedConfiguration')->willThrowException($exception);
        $this->storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->systemConfigDefinitionService, $this->storefrontPluginRegistry);
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
        $this->systemConfigDefinitionService->method('getResolvedConfiguration')->willThrowException(TestExceptionFactory::createException('test'));
        $this->storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->systemConfigDefinitionService, $this->storefrontPluginRegistry);

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
        $this->systemConfigDefinitionService->method('getResolvedConfiguration')->willReturn([
            new SystemConfigTab(
                [
                    new SystemConfigCard(
                        [],
                        []
                    ),
                ]
            ),
        ]);

        $this->storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->systemConfigDefinitionService, $this->storefrontPluginRegistry);

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
