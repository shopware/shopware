<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinitionRegistry;
use Shopware\Core\Framework\App\Feature\AppFeatureLifecycleHandler;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppFeatureLifecycleHandler::class)]
class AppFeatureLifecycleHandlerTest extends TestCase
{
    public function testInstallReattachesKeptFeaturesThenPersistsTheManifest(): void
    {
        $config = new class implements AppFeatureConfig {
            public function getName(): string
            {
                return 'thing';
            }
        };

        $definition = static::createStub(AppFeatureDefinition::class);
        $definition->method('getType')->willReturn('stub');
        $definition->method('getConfigClass')->willReturn($config::class);
        $definition->method('fromApp')->willReturn([$config]);
        $definition->method('toPayload')->willReturn(['k' => 'v']);

        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->once())->method('reattachKeptFeatures')->with('app-id', 'app-name');
        $storage->expects($this->once())->method('forApp')->with('app-id', $config::class)->willReturn([]);
        $storage->expects($this->once())->method('syncForApp')->with('app-id', 'app-name', [
            ['type' => 'stub', 'name' => 'thing', 'payload' => ['k' => 'v']],
        ]);

        $handler = new AppFeatureLifecycleHandler(new AppFeatureDefinitionRegistry([$definition]), $storage);
        $handler->install(AppFixture::createInstallContext(
            AppFixture::createAppEntity('app-name', 'app-id'),
            static::createStub(Manifest::class),
        ));
    }

    public function testPersistValidatesBeforeWritingAndNotifiesAfter(): void
    {
        $config = new class implements AppFeatureConfig {
            public function getName(): string
            {
                return 'thing';
            }
        };

        $calls = [];

        $definition = $this->createMock(AppFeatureDefinition::class);
        $definition->method('getType')->willReturn('stub');
        $definition->method('getConfigClass')->willReturn($config::class);
        $definition->method('fromApp')->willReturn([$config]);
        $definition->method('toPayload')->willReturn(['k' => 'v']);
        $definition->expects($this->once())->method('validate')->with([$config])
            ->willReturnCallback(function () use (&$calls): void {
                $calls[] = 'validate';
            });
        $definition->expects($this->once())->method('persisted')->with([$config])
            ->willReturnCallback(function () use (&$calls): void {
                $calls[] = 'persisted';
            });

        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->method('forApp')->willReturn([]);
        $storage->expects($this->once())->method('syncForApp')
            ->willReturnCallback(function () use (&$calls): void {
                $calls[] = 'sync';
            });

        $handler = new AppFeatureLifecycleHandler(new AppFeatureDefinitionRegistry([$definition]), $storage);
        $handler->update(AppFixture::createInstallContext(
            AppFixture::createAppEntity('app-name', 'app-id'),
            static::createStub(Manifest::class),
        ));

        static::assertSame(['validate', 'sync', 'persisted'], $calls);
    }

    public function testUninstallKeepingUserDataLeavesTheFeatures(): void
    {
        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->never())->method('deleteForApp');

        $handler = new AppFeatureLifecycleHandler(new AppFeatureDefinitionRegistry([]), $storage);
        $handler->uninstall($this->removalContext(keepUserData: true));
    }

    public function testUninstallRemovesTheFeatures(): void
    {
        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->once())->method('deleteForApp')->with('app-id');

        $handler = new AppFeatureLifecycleHandler(new AppFeatureDefinitionRegistry([]), $storage);
        $handler->uninstall($this->removalContext(keepUserData: false));
    }

    public function testDeleteRemovesTheFeatures(): void
    {
        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->once())->method('deleteForApp')->with('app-id');

        $handler = new AppFeatureLifecycleHandler(new AppFeatureDefinitionRegistry([]), $storage);
        $handler->delete($this->removalContext(keepUserData: false));
    }

    private function removalContext(bool $keepUserData): AppRemovalContext
    {
        return new AppRemovalContext(
            AppFixture::createAppEntity('app-name', 'app-id'),
            Context::createDefaultContext(),
            $keepUserData,
        );
    }
}
