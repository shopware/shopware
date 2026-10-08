<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\McpLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpPromptPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpResourcePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpLifecycleHandler::class)]
class McpLifecycleHandlerTest extends TestCase
{
    private const APP_ID = 'app-id-1';
    private const LOCALE = 'en-GB';

    public function testPersistWithoutMcpXmlPassesNullToAllPersisters(): void
    {
        $manifest = static::createStub(Manifest::class);
        $context = Context::createDefaultContext();
        $app = (new AppEntity())->assign(['id' => self::APP_ID]);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('has')->willReturn(false);
        $filesystem->expects($this->never())->method('path');

        $toolPersister = $this->createMock(McpToolPersister::class);
        $promptPersister = $this->createMock(McpPromptPersister::class);
        $resourcePersister = $this->createMock(McpResourcePersister::class);

        $toolPersister->expects($this->once())->method('validateRequiredPrivileges')->with($manifest, null);
        $toolPersister->expects($this->once())->method('persist')->with(null, self::APP_ID, self::LOCALE, $context);
        $promptPersister->expects($this->once())->method('persist')->with(null, self::APP_ID, self::LOCALE, $context);
        $resourcePersister->expects($this->once())->method('persist')->with(null, self::APP_ID, self::LOCALE, $context);

        $persister = new McpLifecycleHandler($toolPersister, $promptPersister, $resourcePersister);
        $persister->install(new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: $context,
            appFilesystem: $filesystem,
            defaultLocale: self::LOCALE,
        ));
    }

    public function testPersistWithMcpXmlPassesParsedMcpToAllPersisters(): void
    {
        $manifest = static::createStub(Manifest::class);
        $context = Context::createDefaultContext();
        $app = (new AppEntity())->assign(['id' => self::APP_ID]);

        $fixturePath = __DIR__ . '/../../_fixtures/Resources/mcp.xml';

        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('has')->willReturn(true);
        $filesystem->method('path')->willReturn($fixturePath);

        $toolPersister = $this->createMock(McpToolPersister::class);
        $promptPersister = $this->createMock(McpPromptPersister::class);
        $resourcePersister = $this->createMock(McpResourcePersister::class);

        $toolPersister->expects($this->once())
            ->method('validateRequiredPrivileges')
            ->with($manifest, static::isInstanceOf(Mcp::class));
        $toolPersister->expects($this->once())
            ->method('persist')
            ->with(static::isInstanceOf(Mcp::class), self::APP_ID, self::LOCALE, $context);
        $promptPersister->expects($this->once())
            ->method('persist')
            ->with(static::isInstanceOf(Mcp::class), self::APP_ID, self::LOCALE, $context);
        $resourcePersister->expects($this->once())
            ->method('persist')
            ->with(static::isInstanceOf(Mcp::class), self::APP_ID, self::LOCALE, $context);

        $persister = new McpLifecycleHandler($toolPersister, $promptPersister, $resourcePersister);
        $persister->install(new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: $context,
            appFilesystem: $filesystem,
            defaultLocale: self::LOCALE,
        ));
    }

    public function testValidationFailureStopsPersistence(): void
    {
        $manifest = static::createStub(Manifest::class);
        $context = Context::createDefaultContext();
        $app = (new AppEntity())->assign(['id' => self::APP_ID]);

        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('has')->willReturn(false);

        $toolPersister = $this->createMock(McpToolPersister::class);
        $promptPersister = $this->createMock(McpPromptPersister::class);
        $resourcePersister = $this->createMock(McpResourcePersister::class);

        $toolPersister->method('validateRequiredPrivileges')
            ->willThrowException(new \RuntimeException('missing privilege'));
        $toolPersister->expects($this->never())->method('persist');
        $promptPersister->expects($this->never())->method('persist');
        $resourcePersister->expects($this->never())->method('persist');

        $persister = new McpLifecycleHandler($toolPersister, $promptPersister, $resourcePersister);

        $this->expectException(\RuntimeException::class);
        $persister->install(new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: $context,
            appFilesystem: $filesystem,
            defaultLocale: self::LOCALE,
        ));
    }

    public function testPersistNotifiesMergedExistingAndNewCapabilities(): void
    {
        $manifest = static::createStub(Manifest::class);
        $context = Context::createDefaultContext();
        $app = (new AppEntity())->assign(['id' => self::APP_ID]);

        $fixturePath = __DIR__ . '/../../_fixtures/Resources/mcp.xml';

        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('has')->willReturn(true);
        $filesystem->method('path')->willReturn($fixturePath);

        $toolPersister = $this->createMock(McpToolPersister::class);
        $promptPersister = $this->createMock(McpPromptPersister::class);
        $resourcePersister = $this->createMock(McpResourcePersister::class);

        $toolPersister->expects($this->once())->method('validateRequiredPrivileges');
        $toolPersister->expects($this->once())->method('persist');
        $promptPersister->expects($this->once())->method('persist');
        $resourcePersister->expects($this->once())->method('persist');

        $capabilityDetector = new class(static::createStub(Connection::class)) extends AppMcpCapabilityDetector {
            public int $persistedCalls = 0;

            public int $fromMcpCalls = 0;

            public ?string $appId = null;

            public ?Mcp $mcp = null;

            public function __construct(Connection $connection)
            {
                parent::__construct($connection);
            }

            public function persistedForApp(string $appId): McpListChangedNotificationSet
            {
                ++$this->persistedCalls;
                $this->appId = $appId;

                return new McpListChangedNotificationSet(tools: true, resources: false, prompts: false);
            }

            public function fromMcp(?Mcp $mcp): McpListChangedNotificationSet
            {
                ++$this->fromMcpCalls;
                $this->mcp = $mcp;

                return new McpListChangedNotificationSet(tools: false, resources: true, prompts: true);
            }
        };

        $notifier = new class(new McpSessionRegistry(new Psr16Cache(new ArrayAdapter()))) extends McpListChangedNotifier {
            public ?McpListChangedNotificationSet $notifications = null;

            public function __construct(McpSessionRegistry $sessionRegistry)
            {
                parent::__construct(null, $sessionRegistry);
            }

            public function notify(McpListChangedNotificationSet $notifications): void
            {
                $this->notifications = $notifications;
            }
        };

        $persister = new McpLifecycleHandler($toolPersister, $promptPersister, $resourcePersister, $capabilityDetector, $notifier);
        $persister->install(new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: $context,
            appFilesystem: $filesystem,
            defaultLocale: self::LOCALE,
        ));

        static::assertSame(1, $capabilityDetector->persistedCalls);
        static::assertSame(1, $capabilityDetector->fromMcpCalls);
        static::assertSame(self::APP_ID, $capabilityDetector->appId);
        static::assertInstanceOf(Mcp::class, $capabilityDetector->mcp);
        static::assertNotNull($notifier->notifications);
        static::assertTrue($notifier->notifications->tools);
        static::assertTrue($notifier->notifications->resources);
        static::assertTrue($notifier->notifications->prompts);
    }
}
