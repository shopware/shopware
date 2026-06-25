<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AppAdministrationSnippetPersister;
use Shopware\Administration\Snippet\AppLifecycleSubscriber;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[CoversClass(AppLifecycleSubscriber::class)]
class AppLifecycleSubscriberTest extends TestCase
{
    private AppLifecycleSubscriber $subscriber;

    private SourceResolver&Stub $sourceResolver;

    private AppAdministrationSnippetPersister&MockObject $persister;

    private AppEntity $app;

    private Context $context;

    private Manifest&Stub $manifest;

    protected function setUp(): void
    {
        $this->sourceResolver = static::createStub(SourceResolver::class);
        $this->persister = $this->createMock(AppAdministrationSnippetPersister::class);
        $this->manifest = static::createStub(Manifest::class);

        $this->app = new AppEntity();
        $this->app->setId('app-id');
        $this->context = Context::createDefaultContext();

        $this->subscriber = new AppLifecycleSubscriber($this->sourceResolver, $this->persister);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->persister->expects($this->never())->method('updateSnippets');

        $events = AppLifecycleSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(AppInstalledEvent::class, $events);
        static::assertArrayHasKey(AppUpdatedEvent::class, $events);
        static::assertSame('onAppUpdate', $events[AppInstalledEvent::class]);
        static::assertSame('onAppUpdate', $events[AppUpdatedEvent::class]);
    }

    public function testOnAppUpdateWithAppUpdatedEvent(): void
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('has')->willReturn(false);

        $this->sourceResolver->method('filesystemForApp')->willReturn($filesystem);

        $this->persister->expects($this->once())
            ->method('updateSnippets')
            ->with($this->app, [], $this->context);

        $event = new AppUpdatedEvent($this->app, $this->manifest, $this->context);
        $this->subscriber->onAppUpdate($event);
    }

    public function testOnAppUpdateWithMultipleSnippetFiles(): void
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('has')->willReturn(true);

        $file1 = static::createStub(SplFileInfo::class);
        $file1->method('getFilenameWithoutExtension')->willReturn('en_GB');
        $file1->method('getContents')->willReturn('{"test": "value"}');

        $file2 = static::createStub(SplFileInfo::class);
        $file2->method('getFilenameWithoutExtension')->willReturn('de_DE');
        $file2->method('getContents')->willReturn('{"test": "wert"}');

        $filesystem->method('findFiles')->willReturn([$file1, $file2]);

        $this->sourceResolver->method('filesystemForApp')->willReturn($filesystem);

        $expectedSnippets = [
            'en_GB' => '{"test": "value"}',
            'de_DE' => '{"test": "wert"}',
        ];

        $this->persister->expects($this->once())
            ->method('updateSnippets')
            ->with($this->app, $expectedSnippets, $this->context);

        $event = new AppUpdatedEvent($this->app, $this->manifest, $this->context);
        $this->subscriber->onAppUpdate($event);
    }
}
