<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Uuid\Uuid;
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
    public function testDoesNothingIfNoPluginsConfigured(): void
    {
        $repo = $this->createMock(EntityRepository::class); // plugin repo
        $lifecycle = $this->createMock(PluginLifecycleService::class);
        $appRepo = $this->createMock(EntityRepository::class);
        $appState = $this->createMock(AppStateService::class);

        $repo->expects($this->never())->method('search');
        $lifecycle->expects($this->never())->method('deactivatePlugin');
        $appRepo->expects($this->never())->method('search');
        $appState->expects($this->never())->method('deactivateApp');

        $handler = new StagingExtensionHandler($repo, $lifecycle, $appRepo, $appState, []);

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $this->createMock(SymfonyStyle::class),
            false,
            [],
        ));
    }

    public function testDeactivatesConfiguredActiveApps(): void
    {
        $context = Context::createDefaultContext();

        $pluginRepo = $this->createMock(EntityRepository::class);
        $pluginRepo->method('search')->willReturnCallback(function (Criteria $criteria, Context $passedContext): EntitySearchResult {
            return new EntitySearchResult('plugin', 0, new PluginCollection([]), null, $criteria, $passedContext);
        });
        $lifecycle = $this->createMock(PluginLifecycleService::class);
        $lifecycle->expects($this->never())->method('deactivatePlugin');

        $appRepo = $this->createMock(EntityRepository::class);
        $appRepo->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $passedContext): EntitySearchResult {
                $active = new AppEntity();
                $active->setId(Uuid::randomHex());
                $active->setName('ActiveApp');
                $active->setActive(true);

                $inactive = new AppEntity();
                $inactive->setId(Uuid::randomHex());
                $inactive->setName('InactiveApp');
                $inactive->setActive(false);

                $collection = new AppCollection([$active, $inactive]);

                return new EntitySearchResult('app', $collection->count(), $collection, null, $criteria, $passedContext);
            });

        $appState = $this->createMock(AppStateService::class);
        $appState->expects($this->once())
            ->method('deactivateApp')
            ->with(
                static::callback(static fn (string $id): bool => \is_string($id) && $id !== ''),
                static::isInstanceOf(Context::class)
            );

        $io = $this->createMock(SymfonyStyle::class);

        $handler = new StagingExtensionHandler($pluginRepo, $lifecycle, $appRepo, $appState, ['ActiveApp', 'InactiveApp']);

        $handler(new SetupStagingEvent(
            $context,
            $io,
            false,
            [],
        ));
    }

    public function testLogsInfoForMissingApps(): void
    {
        $context = Context::createDefaultContext();

        $pluginRepo = $this->createMock(EntityRepository::class);
        $pluginRepo->method('search')->willReturnCallback(function (Criteria $criteria, Context $passedContext): EntitySearchResult {
            return new EntitySearchResult('plugin', 0, new PluginCollection([]), null, $criteria, $passedContext);
        });
        $lifecycle = $this->createMock(PluginLifecycleService::class);
        $lifecycle->expects($this->never())->method('deactivatePlugin');

        $appRepo = $this->createMock(EntityRepository::class);
        $appRepo->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $passedContext): EntitySearchResult {
                return new EntitySearchResult('app', 0, new AppCollection([]), null, $criteria, $passedContext);
            });

        $appState = $this->createMock(AppStateService::class);
        $appState->expects($this->never())->method('deactivateApp');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->atLeastOnce())
            ->method('warning')
            ->with(static::callback(static function (string $message): bool {
                return str_contains($message, 'not found') && str_contains($message, 'MissingApp');
            }));

        $handler = new StagingExtensionHandler($pluginRepo, $lifecycle, $appRepo, $appState, ['MissingApp']);

        $handler(new SetupStagingEvent(
            $context,
            $io,
            false,
            [],
        ));
    }

    public function testDeactivatesConfiguredActivePlugins(): void
    {
        $context = Context::createDefaultContext();

        $repo = $this->createMock(EntityRepository::class); // plugin repo
        $repo->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $passedContext): EntitySearchResult {
                $active = new PluginEntity();
                $active->setId(Uuid::randomHex());
                $active->setName('ActivePlugin');
                $active->setActive(true);
                $active->setInstalledAt(new \DateTime());

                $inactive = new PluginEntity();
                $inactive->setId(Uuid::randomHex());
                $inactive->setName('InactivePlugin');
                $inactive->setActive(false);
                $inactive->setInstalledAt(new \DateTime());

                $collection = new PluginCollection([$active, $inactive]);

                return new EntitySearchResult('plugin', $collection->count(), $collection, null, $criteria, $passedContext);
            });

        $lifecycle = $this->createMock(PluginLifecycleService::class);
        $lifecycle->expects($this->once())
            ->method('deactivatePlugin')
            ->with(
                static::callback(static fn (PluginEntity $p) => $p->getName() === 'ActivePlugin' && $p->getActive() === true),
                static::isInstanceOf(Context::class)
            );

        $appRepo = $this->createMock(EntityRepository::class);
        $appRepo->method('search')->willReturnCallback(function (Criteria $criteria, Context $passedContext): EntitySearchResult {
            $apps = new AppCollection([]);

            return new EntitySearchResult('app', 0, $apps, null, $criteria, $passedContext);
        });
        $appState = $this->createMock(AppStateService::class);
        $appState->expects($this->never())->method('deactivateApp');

        $io = $this->createMock(SymfonyStyle::class);

        $handler = new StagingExtensionHandler($repo, $lifecycle, $appRepo, $appState, ['ActivePlugin', 'InactivePlugin']);

        $handler(new SetupStagingEvent(
            $context,
            $io,
            false,
            [],
        ));
    }

    public function testLogsInfoForMissingPlugins(): void
    {
        $context = Context::createDefaultContext();

        $repo = $this->createMock(EntityRepository::class); // plugin repo
        $repo->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $passedContext): EntitySearchResult {
                return new EntitySearchResult('plugin', 0, new PluginCollection([]), null, $criteria, $passedContext);
            });

        $lifecycle = $this->createMock(PluginLifecycleService::class);
        $lifecycle->expects($this->never())->method('deactivatePlugin');

        $appRepo = $this->createMock(EntityRepository::class);
        $appRepo->method('search')->willReturnCallback(function (Criteria $criteria, Context $passedContext): EntitySearchResult {
            $apps = new AppCollection([]);

            return new EntitySearchResult('app', 0, $apps, null, $criteria, $passedContext);
        });
        $appState = $this->createMock(AppStateService::class);
        $appState->expects($this->never())->method('deactivateApp');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->atLeastOnce())
            ->method('warning')
            ->with(static::callback(static function (string $message): bool {
                return str_contains($message, 'not found') && str_contains($message, 'MissingPlugin');
            }));

        $handler = new StagingExtensionHandler($repo, $lifecycle, $appRepo, $appState, ['MissingPlugin']);

        $handler(new SetupStagingEvent(
            $context,
            $io,
            false,
            [],
        ));
    }
}
