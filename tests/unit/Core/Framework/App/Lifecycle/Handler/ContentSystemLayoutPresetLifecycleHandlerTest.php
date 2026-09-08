<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\ContentSystemLayoutPresetLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemLayoutPresetPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutPresetLifecycleHandler::class)]
class ContentSystemLayoutPresetLifecycleHandlerTest extends TestCase
{
    #[TestDox('install persists the app presets')]
    public function testInstallPersists(): void
    {
        $context = $this->buildPersistContext();

        $persister = $this->createMock(ContentSystemLayoutPresetPersister::class);
        $persister->expects($this->once())->method('persist')->with($context);

        (new ContentSystemLayoutPresetLifecycleHandler($persister))->install($context);
    }

    #[TestDox('update persists the app presets')]
    public function testUpdatePersists(): void
    {
        $context = $this->buildPersistContext();

        $persister = $this->createMock(ContentSystemLayoutPresetPersister::class);
        $persister->expects($this->once())->method('persist')->with($context);

        (new ContentSystemLayoutPresetLifecycleHandler($persister))->update($context);
    }

    #[TestDox('propagates a persister failure on install rather than swallowing it')]
    public function testInstallPropagatesPersisterException(): void
    {
        $persister = static::createStub(ContentSystemLayoutPresetPersister::class);
        $persister->method('persist')->willThrowException(new \RuntimeException('persist failed'));

        $this->expectExceptionObject(new \RuntimeException('persist failed'));

        (new ContentSystemLayoutPresetLifecycleHandler($persister))->install($this->buildPersistContext());
    }

    private function buildPersistContext(): AppPersistContext
    {
        $app = new AppEntity();
        $app->setId('app-id');
        $app->setName('DemoApp');

        return new AppPersistContext(
            manifest: static::createStub(Manifest::class),
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: static::createStub(Filesystem::class),
            defaultLocale: 'en-GB',
        );
    }
}
