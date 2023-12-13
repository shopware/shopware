<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Steps;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopware\Core\Framework\Update\Steps\DeactivateExtensionsStep;
use Shopware\Core\Framework\Update\Steps\ValidResult;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[CoversClass(DeactivateExtensionsStep::class)]
class DeactivateExtensionsStepTest extends TestCase
{
    public function testRunWithEmptyPlugins(): void
    {
        $version = new Version();
        $version->assign([
            'version' => '6.6.0.0',
        ]);

        $deactivateExtensionsStep = new DeactivateExtensionsStep(
            $version,
            ExtensionCompatibility::PLUGIN_DEACTIVATION_FILTER_ALL,
            $this->createMock(ExtensionCompatibility::class),
            $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(SystemConfigService::class),
            Context::createDefaultContext()
        );

        $result = $deactivateExtensionsStep->run(0);

        static::assertSame($result->getTotal(), $result->getOffset());
    }

    public function testRunShouldDeactivateOneAndFinishDirectly(): void
    {
        $version = new Version();
        $version->assign([
            'version' => '6.6.0.0',
        ]);

        $extension = new ExtensionStruct();
        $extension->setId(1);
        $extension->setName('TestApp');
        $extension->setType(ExtensionStruct::EXTENSION_TYPE_APP);

        $pluginCompatibility = $this->createMock(ExtensionCompatibility::class);
        $pluginCompatibility
            ->method('getExtensionsToDeactivate')
            ->willReturn([$extension]);

        $systemConfigService = $this->createMock(SystemConfigService::class);

        $systemConfigService
            ->expects(static::once())
            ->method('set')
            ->with(DeactivateExtensionsStep::UPDATE_DEACTIVATED_PLUGINS, [1]);

        $extensionLifecycleService = $this->createMock(ExtensionLifecycleService::class);

        $extensionLifecycleService
            ->expects(static::once())
            ->method('deactivate')
            ->with('app', 'TestApp');

        $deactivateExtensionsStep = new DeactivateExtensionsStep(
            $version,
            ExtensionCompatibility::PLUGIN_DEACTIVATION_FILTER_ALL,
            $pluginCompatibility,
            $extensionLifecycleService,
            $systemConfigService,
            Context::createDefaultContext()
        );

        $result = $deactivateExtensionsStep->run(0);

        static::assertSame($result->getTotal(), $result->getOffset());
    }

    public function testRunShouldDeactivateMultiple(): void
    {
        $version = new Version();
        $version->assign([
            'version' => '6.6.0.0',
        ]);

        $extension = new ExtensionStruct();
        $extension->setId(1);
        $extension->setName('TestApp');
        $extension->setType(ExtensionStruct::EXTENSION_TYPE_APP);

        $pluginCompatibility = $this->createMock(ExtensionCompatibility::class);
        $pluginCompatibility
            ->method('getExtensionsToDeactivate')
            ->willReturn([$extension, $extension]);

        $systemConfigService = $this->createMock(SystemConfigService::class);

        $systemConfigService
            ->expects(static::once())
            ->method('set')
            ->with(DeactivateExtensionsStep::UPDATE_DEACTIVATED_PLUGINS, [1]);

        $extensionLifecycleService = $this->createMock(ExtensionLifecycleService::class);

        $extensionLifecycleService
            ->expects(static::once())
            ->method('deactivate')
            ->with('app', 'TestApp');

        $deactivateExtensionsStep = new DeactivateExtensionsStep(
            $version,
            ExtensionCompatibility::PLUGIN_DEACTIVATION_FILTER_ALL,
            $pluginCompatibility,
            $extensionLifecycleService,
            $systemConfigService,
            Context::createDefaultContext()
        );

        $result = $deactivateExtensionsStep->run(0);

        static::assertInstanceOf(ValidResult::class, $result);
        static::assertSame(1, $result->getOffset());
    }
}
