<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
use Shopware\Core\Maintenance\Staging\Handler\StagingPluginHandler;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StagingPluginHandler::class)]
class StagingPluginHandlerTest extends TestCase
{
    public function testDoesNothingIfNoPluginsConfigured(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $lifecycle = $this->createMock(PluginLifecycleService::class);

        $repo->expects($this->never())->method('search');
        $lifecycle->expects($this->never())->method('deactivatePlugin');

        $handler = new StagingPluginHandler($repo, $lifecycle, []);

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $this->createMock(SymfonyStyle::class),
            false,
            [],
        ));
    }

    public function testDeactivatesConfiguredActivePlugins(): void
    {
        $context = Context::createDefaultContext();

        $repo = $this->createMock(EntityRepository::class);
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

        $io = $this->createMock(SymfonyStyle::class);

        $handler = new StagingPluginHandler($repo, $lifecycle, ['ActivePlugin', 'InactivePlugin']);

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

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $passedContext): EntitySearchResult {
                return new EntitySearchResult('plugin', 0, new PluginCollection([]), null, $criteria, $passedContext);
            });

        $lifecycle = $this->createMock(PluginLifecycleService::class);
        $lifecycle->expects($this->never())->method('deactivatePlugin');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->atLeastOnce())
            ->method('warning')
            ->with(static::callback(static function (string $message): bool {
                return str_contains($message, 'not found') && str_contains($message, 'MissingPlugin');
            }));

        $handler = new StagingPluginHandler($repo, $lifecycle, ['MissingPlugin']);

        $handler(new SetupStagingEvent(
            $context,
            $io,
            false,
            [],
        ));
    }
}
