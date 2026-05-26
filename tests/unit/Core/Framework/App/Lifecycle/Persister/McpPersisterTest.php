<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpPromptPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpResourcePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[CoversClass(McpPersister::class)]
class McpPersisterTest extends TestCase
{
    private const APP_ID = 'app-id-1';
    private const LOCALE = 'en-GB';

    public function testPersistWithoutMcpXmlPassesNullToAllPersisters(): void
    {
        $manifest = $this->createMock(Manifest::class);
        $context = Context::createDefaultContext();
        $app = (new AppEntity())->assign(['id' => self::APP_ID]);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('has')->with('Resources/mcp.xml')->willReturn(false);
        $filesystem->expects($this->never())->method('path');

        $toolPersister = $this->createMock(McpToolPersister::class);
        $promptPersister = $this->createMock(McpPromptPersister::class);
        $resourcePersister = $this->createMock(McpResourcePersister::class);

        $toolPersister->expects($this->once())->method('validateRequiredPrivileges')->with($manifest, null);
        $toolPersister->expects($this->once())->method('persist')->with(null, self::APP_ID, self::LOCALE, $context);
        $promptPersister->expects($this->once())->method('persist')->with(null, self::APP_ID, self::LOCALE, $context);
        $resourcePersister->expects($this->once())->method('persist')->with(null, self::APP_ID, self::LOCALE, $context);

        $persister = new McpPersister($toolPersister, $promptPersister, $resourcePersister);
        $persister->persist(new AppLifecycleContext(
            manifest: $manifest,
            app: $app,
            context: $context,
            appFilesystem: $filesystem,
            defaultLocale: self::LOCALE,
            isInstall: true,
        ));
    }

    public function testPersistWithMcpXmlPassesParsedMcpToAllPersisters(): void
    {
        $manifest = $this->createMock(Manifest::class);
        $context = Context::createDefaultContext();
        $app = (new AppEntity())->assign(['id' => self::APP_ID]);

        $fixturePath = __DIR__ . '/../../_fixtures/Resources/mcp.xml';

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('has')->with('Resources/mcp.xml')->willReturn(true);
        $filesystem->method('path')->with('Resources/mcp.xml')->willReturn($fixturePath);

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

        $persister = new McpPersister($toolPersister, $promptPersister, $resourcePersister);
        $persister->persist(new AppLifecycleContext(
            manifest: $manifest,
            app: $app,
            context: $context,
            appFilesystem: $filesystem,
            defaultLocale: self::LOCALE,
            isInstall: true,
        ));
    }

    public function testValidationFailureStopsPersistence(): void
    {
        $manifest = $this->createMock(Manifest::class);
        $context = Context::createDefaultContext();
        $app = (new AppEntity())->assign(['id' => self::APP_ID]);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('has')->willReturn(false);

        $toolPersister = $this->createMock(McpToolPersister::class);
        $promptPersister = $this->createMock(McpPromptPersister::class);
        $resourcePersister = $this->createMock(McpResourcePersister::class);

        $toolPersister->method('validateRequiredPrivileges')
            ->willThrowException(new \RuntimeException('missing privilege'));
        $toolPersister->expects($this->never())->method('persist');
        $promptPersister->expects($this->never())->method('persist');
        $resourcePersister->expects($this->never())->method('persist');

        $persister = new McpPersister($toolPersister, $promptPersister, $resourcePersister);

        $this->expectException(\RuntimeException::class);
        $persister->persist(new AppLifecycleContext(
            manifest: $manifest,
            app: $app,
            context: $context,
            appFilesystem: $filesystem,
            defaultLocale: self::LOCALE,
            isInstall: true,
        ));
    }
}
