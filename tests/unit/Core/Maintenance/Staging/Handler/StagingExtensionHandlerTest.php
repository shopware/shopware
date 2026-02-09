<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\ExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\Maintenance\Staging\Handler\StagingExtensionHandler;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StagingExtensionHandler::class)]
class StagingExtensionHandlerTest extends TestCase
{
    public function testDoesNothingIfNoExtensionsConfigured(): void
    {
        $dataProvider = $this->createMock(ExtensionDataProvider::class);
        $lifecycle = $this->createMock(ExtensionLifecycleService::class);

        $dataProvider->expects($this->never())->method('getInstalledExtensions');
        $lifecycle->expects($this->never())->method('deactivate');

        $handler = new StagingExtensionHandler(
            $this->createMock(Kernel::class),
            $dataProvider,
            $lifecycle,
        );

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $this->createMock(SymfonyStyle::class),
            false,
            [],
            [],
        ));
    }

    public function testDeactivatesConfiguredActiveExtensions(): void
    {
        $context = Context::createDefaultContext();

        $activePlugin = new ExtensionStruct();
        $activePlugin->setName('ActivePlugin');
        $activePlugin->setActive(true);
        $activePlugin->setType(ExtensionStruct::EXTENSION_TYPE_PLUGIN);

        $inactiveApp = new ExtensionStruct();
        $inactiveApp->setName('InactiveApp');
        $inactiveApp->setActive(false);
        $inactiveApp->setType(ExtensionStruct::EXTENSION_TYPE_APP);

        $extensions = new ExtensionCollection([$activePlugin, $inactiveApp]);

        $dataProvider = $this->createMock(ExtensionDataProvider::class);
        $dataProvider
            ->expects($this->once())
            ->method('getInstalledExtensions')
            ->willReturnCallback(function (Context $passedContext, bool $loadCloudExtensions, $criteria) use ($extensions) {
                // The handler passes a Criteria filtered by names; we just return our collection
                return $extensions;
            });

        $lifecycle = $this->createMock(ExtensionLifecycleService::class);
        $lifecycle
            ->expects($this->once())
            ->method('deactivate')
            ->with(
                // Only the active extension should be deactivated
                static::callback(static fn (string $type): bool => $type === ExtensionStruct::EXTENSION_TYPE_PLUGIN),
                static::callback(static fn (string $name): bool => $name === 'ActivePlugin'),
                static::isInstanceOf(Context::class)
            );

        $io = $this->createMock(SymfonyStyle::class);

        $handler = new StagingExtensionHandler(
            $this->createMock(Kernel::class),
            $dataProvider,
            $lifecycle,
        );

        $handler(new SetupStagingEvent(
            $context,
            $io,
            false,
            [],
            ['ActivePlugin', 'InactiveApp'],
        ));
    }

    public function testLogsInfoForMissingExtensions(): void
    {
        $context = Context::createDefaultContext();

        $dataProvider = $this->createMock(ExtensionDataProvider::class);
        $dataProvider
            ->expects($this->once())
            ->method('getInstalledExtensions')
            ->willReturn(new ExtensionCollection([]));

        $lifecycle = $this->createMock(ExtensionLifecycleService::class);
        $lifecycle->expects($this->never())->method('deactivate');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->atLeastOnce())
            ->method('warning')
            ->with(static::callback(static function (string $message): bool {
                return str_contains($message, 'not found') && str_contains($message, 'MissingExtension');
            }));

        $handler = new StagingExtensionHandler(
            $this->createMock(Kernel::class),
            $dataProvider,
            $lifecycle,
        );

        $handler(new SetupStagingEvent(
            $context,
            $io,
            false,
            [],
            ['MissingExtension'],
        ));
    }

    public function testSkipsAlreadyInactiveExtensions(): void
    {
        $context = Context::createDefaultContext();

        $inactive = new ExtensionStruct();
        $inactive->setName('AlreadyInactive');
        $inactive->setActive(false);
        $inactive->setType(ExtensionStruct::EXTENSION_TYPE_PLUGIN);

        $extensions = new ExtensionCollection([$inactive]);

        $dataProvider = $this->createMock(ExtensionDataProvider::class);
        $dataProvider
            ->expects($this->once())
            ->method('getInstalledExtensions')
            ->willReturn($extensions);

        $lifecycle = $this->createMock(ExtensionLifecycleService::class);
        $lifecycle->expects($this->never())->method('deactivate');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->atLeastOnce())
            ->method('comment')
            ->with(static::callback(static function (string $message): bool {
                return str_contains($message, 'already inactive') && str_contains($message, 'AlreadyInactive');
            }));

        $handler = new StagingExtensionHandler(
            $this->createMock(Kernel::class),
            $dataProvider,
            $lifecycle,
        );

        $handler(new SetupStagingEvent(
            $context,
            $io,
            false,
            [],
            ['AlreadyInactive'],
        ));
    }
}
