<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\System\CustomEntity\CustomEntityRegistrar;
use Shopware\Core\System\CustomEntity\Schema\DynamicEntityDefinition;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(CustomEntityRegistrar::class)]
class CustomEntityRegistrarTest extends TestCase
{
    public function testSkipsRegistrationIfDbalIsNotConnected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')
            ->willReturn(false);
        $connection->expects(static::never())
            ->method('fetchAllAssociative');

        $container = new Container();
        $container->set(Connection::class, $connection);

        $registrar = new CustomEntityRegistrar($container);

        $registrar->register();
    }

    public function testSkipsRegistrationIfFetchingCustomEntitiesFailWithException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')
            ->willReturn(true);
        $connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willThrowException(new Exception());

        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionInstanceRegistry->expects(static::never())
            ->method('register');

        $container = new Container();
        $container->set(Connection::class, $connection);
        $container->set(DefinitionInstanceRegistry::class, $definitionInstanceRegistry);

        $registrar = new CustomEntityRegistrar($container);

        $registrar->register();

        static::assertCount(3, $container->getServiceIds());
    }

    public function testFetchesCustomEntitiesFromDbAndRegistersThemAtTheContainer(): void
    {
        $container = new Container();

        /** @var DynamicEntityDefinition[] $definitions */
        $definitions = [
            DynamicEntityDefinition::create('ce_test_one', [], [], $container),
            DynamicEntityDefinition::create('ce_test_two', [], [], $container),
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')
            ->willReturn(true);
        $connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'name' => 'ce_test_one',
                    'fields' => json_encode([]),
                    'flags' => json_encode([]),
                ],
                [
                    'name' => 'ce_test_two',
                    'fields' => json_encode([]),
                    'flags' => json_encode([]),
                ],
            ]);

        $container->set(Connection::class, $connection);
        $container->set(DefinitionInstanceRegistry::class, new DefinitionInstanceRegistry($container, [], []));
        $container->set(EntityReaderInterface::class, $this->createMock(EntityReaderInterface::class));
        $container->set(VersionManager::class, $this->createMock(VersionManager::class));
        $container->set(EntitySearcherInterface::class, $this->createMock(EntitySearcherInterface::class));
        $container->set(EntityAggregatorInterface::class, $this->createMock(EntityAggregatorInterface::class));
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
        $container->set(EntityLoadedEventFactory::class, $this->createMock(EntityLoadedEventFactory::class));

        $registrar = new CustomEntityRegistrar($container);

        $registrar->register();

        static::assertInstanceOf(DynamicEntityDefinition::class, $definitions[0]);
        static::assertSame('ce_test_one', $definitions[0]->getEntityName());
        static::assertInstanceOf(EntityRepository::class, $container->get($definitions[0]->getEntityName() . '.repository'));

        static::assertInstanceOf(DynamicEntityDefinition::class, $definitions[1]);
        static::assertSame('ce_test_two', $definitions[1]->getEntityName());
        static::assertInstanceOf(EntityRepository::class, $container->get($definitions[1]->getEntityName() . '.repository'));
    }

    public function testAfterMigrationWithEmptyFlags(): void
    {
        $container = new Container();

        /** @var DynamicEntityDefinition[] $definitions */
        $definitions = [
            DynamicEntityDefinition::create('ce_test_one', [], [], $container),
            DynamicEntityDefinition::create('ce_test_two', [], [], $container),
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('isConnected')
            ->willReturn(true);
        $connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'name' => 'ce_test_one',
                    'fields' => json_encode([]),
                    'flags' => '',
                ],
            ]);

        $container->set(Connection::class, $connection);
        $container->set(DefinitionInstanceRegistry::class, new DefinitionInstanceRegistry($container, [], []));
        $container->set(EntityReaderInterface::class, $this->createMock(EntityReaderInterface::class));
        $container->set(VersionManager::class, $this->createMock(VersionManager::class));
        $container->set(EntitySearcherInterface::class, $this->createMock(EntitySearcherInterface::class));
        $container->set(EntityAggregatorInterface::class, $this->createMock(EntityAggregatorInterface::class));
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
        $container->set(EntityLoadedEventFactory::class, $this->createMock(EntityLoadedEventFactory::class));

        $registrar = new CustomEntityRegistrar($container);

        $registrar->register();

        static::assertInstanceOf(DynamicEntityDefinition::class, $definitions[0]);
        static::assertSame('ce_test_one', $definitions[0]->getEntityName());
        static::assertInstanceOf(EntityRepository::class, $container->get($definitions[0]->getEntityName() . '.repository'));
    }
}
