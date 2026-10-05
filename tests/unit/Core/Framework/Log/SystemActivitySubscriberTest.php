<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Event\AppUploadedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Log\SystemActivitySubscriber;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginUploadedEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\MonologBundle\DependencyInjection\Configuration;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemActivitySubscriber::class)]
class SystemActivitySubscriberTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('fetchOne')->willReturn('admin');
    }

    #[DataProvider('creationProvider')]
    public function testLogsOnlyCreatedEntitiesWithoutCredentials(string $entityName): void
    {
        $context = new Context(new AdminApiSource('0123456789abcdef0123456789abcdef', 'fedcba9876543210fedcba9876543210'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('info')->willReturnCallback(static function (string $message, array $data) use ($entityName): void {
            static::assertSame($entityName . ':create', $message);
            static::assertContains($data['entityId'], ['first', 'second']);
            static::assertSame(['entityId', 'actorType', 'userId', 'integrationAccessKey', 'username'], array_keys($data));
            static::assertSame('0123456789abcdef0123456789abcdef', $data['userId']);
            static::assertSame('admin', $data['username']);
            static::assertSame('user', $data['actorType']);
            static::assertSame('admin', $data['integrationAccessKey']);
        });
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SystemActivitySubscriber($logger, $this->connection));
        $event = new EntityWrittenEvent($entityName, [
            new EntityWriteResult('first', ['password' => 'secret', 'secretAccessKey' => 'secret'], $entityName, EntityWriteResult::OPERATION_INSERT),
            new EntityWriteResult('existing', [], $entityName, EntityWriteResult::OPERATION_UPDATE),
            new EntityWriteResult('second', [], $entityName, EntityWriteResult::OPERATION_INSERT),
        ], $context);

        $dispatcher->dispatch($event, $event->getName());
    }

    public static function creationProvider(): \Generator
    {
        yield 'administration user created' => ['user'];
        yield 'integration created' => ['integration'];
    }

    public function testDoesNotLogUpdatesOrOtherEntities(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SystemActivitySubscriber($logger, $this->connection));
        foreach (['user', 'integration', 'product'] as $entityName) {
            $event = new EntityWrittenEvent($entityName, [
                new EntityWriteResult('id', [], $entityName, $entityName === 'product' ? EntityWriteResult::OPERATION_INSERT : EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext());
            $dispatcher->dispatch($event, $event->getName());
        }
    }

    #[DataProvider('appLifecycleProvider')]
    public function testLogsAppLifecycleWithMetadataAndActor(string $action): void
    {
        $app = new AppEntity();
        $app->setName('ExampleApp');
        $app->setVersion('1.0.0');
        $manifest = static::createStub(Manifest::class);
        $metadata = static::createStub(Metadata::class);
        $metadata->method('getVersion')->willReturn('2.0.0');
        $manifest->method('getMetadata')->willReturn($metadata);
        $userId = Uuid::randomHex();
        $context = new Context(new AdminApiSource($userId));
        $event = match ($action) {
            'enable' => new AppActivatedEvent($app, $context),
            'disable' => new AppDeactivatedEvent($app, $context),
            'install' => new AppInstalledEvent($app, $manifest, $context),
            'update' => new AppUpdatedEvent($app, $manifest, $context),
            default => throw new \InvalidArgumentException('Unsupported app action'),
        };
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('app:' . $action, [
            'appName' => 'ExampleApp',
            'appVersion' => \in_array($action, ['install', 'update'], true) ? '2.0.0' : '1.0.0',
            'actorType' => 'user', 'userId' => $userId, 'username' => 'admin',
        ]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SystemActivitySubscriber($logger, $this->connection));

        $dispatcher->dispatch($event);
    }

    public static function appLifecycleProvider(): \Generator
    {
        yield 'app enabled' => ['enable'];
        yield 'app disabled' => ['disable'];
        yield 'app installed uses manifest version' => ['install'];
        yield 'app updated uses target manifest version' => ['update'];
    }

    #[DataProvider('appRemovalProvider')]
    public function testLogsAppRemovalWithDatabaseMetadata(bool $exists, bool $keepUserData): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE app (id BLOB PRIMARY KEY, name VARCHAR(255), version VARCHAR(255))');
        $appId = Uuid::randomHex();
        if ($exists) {
            $connection->insert('app', ['id' => Uuid::fromHexToBytes($appId), 'name' => 'ExampleApp', 'version' => '2.0.0']);
        }
        $data = ['appId' => $appId];
        if ($exists) {
            $data['appName'] = 'ExampleApp';
            $data['appVersion'] = '2.0.0';
        }
        $data['keepUserData'] = $keepUserData;
        $data['actorType'] = 'system';
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('app:uninstall', $data);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SystemActivitySubscriber($logger, $connection));

        $dispatcher->dispatch(new AppDeletedEvent($appId, Context::createCLIContext(), $keepUserData));
        $connection->close();
    }

    public static function appRemovalProvider(): \Generator
    {
        yield 'remove app data' => [true, false];
        yield 'retain app data' => [true, true];
        yield 'missing app omits unavailable metadata' => [false, false];
    }

    public function testLogsAppUploadWithAppMetadataAndActor(): void
    {
        $userId = Uuid::randomHex();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('app:upload', [
            'filename' => 'extension.zip', 'appName' => 'ExampleApp', 'appVersion' => '1.0.0',
            'actorType' => 'user', 'userId' => $userId, 'username' => 'admin',
        ]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SystemActivitySubscriber($logger, $this->connection));

        $dispatcher->dispatch(new AppUploadedEvent('extension.zip', new Context(new AdminApiSource($userId)), 'ExampleApp', '1.0.0'));
    }

    public function testLogsSuccessfulUpload(): void
    {
        $context = new Context(new AdminApiSource('0123456789abcdef0123456789abcdef'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:upload', [
            'filename' => 'plugin.zip', 'pluginName' => 'ExamplePlugin', 'pluginVersion' => '2.0.0', 'actorType' => 'user', 'userId' => '0123456789abcdef0123456789abcdef', 'username' => 'admin',
        ]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SystemActivitySubscriber($logger, $this->connection));

        $dispatcher->dispatch(new PluginUploadedEvent('plugin.zip', $context, 'ExamplePlugin', '2.0.0'));
    }

    /**
     * @param class-string<PluginPostActivateEvent|PluginPostDeactivateEvent|PluginPostInstallEvent|PluginPostUninstallEvent|PluginPostUpdateEvent> $eventClass
     */
    #[DataProvider('lifecycleProvider')]
    public function testLogsCompletedPluginLifecycle(string $eventClass, string $action): void
    {
        $plugin = new PluginEntity();
        $plugin->setId('plugin-id');
        $plugin->setName('ExamplePlugin');
        $event = match ($eventClass) {
            PluginPostActivateEvent::class => new PluginPostActivateEvent($plugin, $this->createLifecycleContext(ActivateContext::class)),
            PluginPostDeactivateEvent::class => new PluginPostDeactivateEvent($plugin, $this->createLifecycleContext(DeactivateContext::class)),
            PluginPostInstallEvent::class => new PluginPostInstallEvent($plugin, $this->createLifecycleContext(InstallContext::class)),
            PluginPostUninstallEvent::class => new PluginPostUninstallEvent($plugin, $this->createLifecycleContext(UninstallContext::class)),
            PluginPostUpdateEvent::class => new PluginPostUpdateEvent($plugin, $this->createLifecycleContext(UpdateContext::class)),
            default => throw new \InvalidArgumentException('Unsupported lifecycle event'),
        };
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:' . $action, [
            'pluginName' => 'ExamplePlugin',
            'pluginVersion' => '1.2.3',
            'actorType' => 'system',
        ]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SystemActivitySubscriber($logger, $this->connection));

        $dispatcher->dispatch($event);
    }

    public static function lifecycleProvider(): \Generator
    {
        yield 'plugin enabled' => [PluginPostActivateEvent::class, 'enable'];
        yield 'plugin disabled' => [PluginPostDeactivateEvent::class, 'disable'];
        yield 'plugin installed' => [PluginPostInstallEvent::class, 'install'];
        yield 'plugin uninstalled' => [PluginPostUninstallEvent::class, 'uninstall'];
    }

    public function testLogsPluginInstallationFromCliAsSystemActivity(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $plugin = new PluginEntity();
        $plugin->setName('ExamplePlugin');
        $context = static::createStub(InstallContext::class);
        $context->method('getContext')->willReturn(Context::createCLIContext());
        $context->method('getCurrentPluginVersion')->willReturn('1.2.3');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:install', [
            'pluginName' => 'ExamplePlugin', 'pluginVersion' => '1.2.3', 'actorType' => 'system',
        ]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SystemActivitySubscriber($logger, $connection));

        $dispatcher->dispatch(new PluginPostInstallEvent($plugin, $context));
        $connection->close();
    }

    public function testLogsUpgradeFromPreviousVersionToNewVersion(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('ExamplePlugin');
        // The lifecycle service has already updated the entity before dispatching the post-update event.
        $plugin->setVersion('2.0.0');
        $context = $this->createLifecycleContext(UpdateContext::class);
        $context->method('getUpdatePluginVersion')->willReturn('2.0.0');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:update', [
            'pluginName' => 'ExamplePlugin',
            'pluginVersion' => '2.0.0',
            'previousPluginVersion' => '1.2.3',
            'actorType' => 'system',
        ]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SystemActivitySubscriber($logger, $this->connection));

        $dispatcher->dispatch(new PluginPostUpdateEvent($plugin, $context));
    }

    public function testSystemActivityChannelUsesBufferedDatabaseLoggingByDefault(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new MonologExtension());
        $loader = new XmlFileLoader(
            $container,
            new FileLocator(\dirname(__DIR__, 5) . '/src/Core/Framework/DependencyInjection')
        );
        $loader->load('services.xml');
        $config = (new Processor())->processConfiguration(new Configuration(), $container->getExtensionConfig('monolog'));
        $buffer = $config['handlers']['business_event_handler_buffer'];
        $handler = $config['handlers'][$buffer['handler']];

        static::assertContains('system_activity', $config['channels']);
        static::assertContains('system_activity', $buffer['channels']['elements']);
        static::assertSame('inclusive', $buffer['channels']['type']);
        static::assertSame('buffer', $buffer['type']);
        static::assertContains('system_activity', $handler['channels']['elements']);
        static::assertSame(DoctrineSQLHandler::class, $handler['id']);

        $subscriber = $container->getDefinition(SystemActivitySubscriber::class);
        static::assertTrue($subscriber->hasTag('kernel.event_subscriber'));
        static::assertSame([['channel' => 'system_activity']], $subscriber->getTag('monolog.logger'));
    }

    public function testUploadOmitsNullMetadataAndPreservesEmptyValues(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:upload', [
            'filename' => 'plugin.zip', 'pluginName' => '', 'actorType' => 'system',
        ]);
        $subscriber = new SystemActivitySubscriber($logger, $this->connection);

        $subscriber->onPluginUploaded(new PluginUploadedEvent('plugin.zip', Context::createDefaultContext(), pluginName: ''));
    }

    public function testResolvesUsernameFromDatabase(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE `user` (id BLOB PRIMARY KEY, username VARCHAR(255))');
        $userId = Uuid::randomHex();
        $connection->insert('user', ['id' => Uuid::fromHexToBytes($userId), 'username' => 'shop-admin']);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:upload', [
            'filename' => 'plugin.zip', 'pluginName' => 'ExamplePlugin', 'pluginVersion' => '2.0.0', 'actorType' => 'user', 'userId' => $userId, 'username' => 'shop-admin',
        ]);
        $subscriber = new SystemActivitySubscriber($logger, $connection);

        $subscriber->onPluginUploaded(new PluginUploadedEvent('plugin.zip', new Context(new AdminApiSource($userId)), 'ExamplePlugin', '2.0.0'));
        $connection->close();
    }

    public function testMissingUserStillLogsActorId(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('fetchOne')->willReturn(false);
        $userId = Uuid::randomHex();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:upload', [
            'filename' => 'plugin.zip', 'pluginName' => 'ExamplePlugin', 'pluginVersion' => '2.0.0', 'actorType' => 'user', 'userId' => $userId,
        ]);
        $subscriber = new SystemActivitySubscriber($logger, $this->connection);

        $subscriber->onPluginUploaded(new PluginUploadedEvent('plugin.zip', new Context(new AdminApiSource($userId)), 'ExamplePlugin', '2.0.0'));
    }

    public function testResolvesIntegrationAccessKeyWithoutLoggingSecret(): void
    {
        // Only the integration table is needed for an integration actor.
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE integration (id BLOB PRIMARY KEY, access_key VARCHAR(255), secret_access_key VARCHAR(255))');
        $integrationId = Uuid::randomHex();
        $connection->insert('integration', ['id' => Uuid::fromHexToBytes($integrationId), 'access_key' => 'SWIAEXAMPLE', 'secret_access_key' => 'secret']);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:upload', [
            'filename' => 'plugin.zip', 'pluginName' => 'ExamplePlugin', 'pluginVersion' => '2.0.0', 'actorType' => 'integration', 'integrationAccessKey' => 'SWIAEXAMPLE',
        ]);
        $subscriber = new SystemActivitySubscriber($logger, $connection);

        $subscriber->onPluginUploaded(new PluginUploadedEvent('plugin.zip', new Context(new AdminApiSource(null, $integrationId)), 'ExamplePlugin', '2.0.0'));
        $connection->close();
    }

    public function testAdminContextWithoutIdentityOmitsActorType(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:upload', ['filename' => 'plugin.zip']);
        $subscriber = new SystemActivitySubscriber($logger, $this->connection);

        $subscriber->onPluginUploaded(new PluginUploadedEvent('plugin.zip', new Context(new AdminApiSource(null))));
    }

    public function testMissingIntegrationOmitsAccessKey(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:upload', [
            'filename' => 'plugin.zip', 'pluginName' => 'ExamplePlugin', 'pluginVersion' => '2.0.0', 'actorType' => 'integration',
        ]);
        $subscriber = new SystemActivitySubscriber($logger, $connection);

        $subscriber->onPluginUploaded(new PluginUploadedEvent('plugin.zip', new Context(new AdminApiSource(null, Uuid::randomHex())), 'ExamplePlugin', '2.0.0'));
    }

    /**
     * @template T of InstallContext
     *
     * @param class-string<T> $contextClass
     *
     * @return T&Stub
     */
    private function createLifecycleContext(string $contextClass): InstallContext
    {
        $context = static::createStub($contextClass);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getCurrentPluginVersion')->willReturn('1.2.3');

        return $context;
    }
}
