<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AppLifecycleSubscriber::class)]
class AppLifecycleSubscriberTest extends TestCase
{
    private SourceResolver&Stub $sourceResolver;

    private AppAdministrationSnippetPersister&Stub $persister;

    private AppEntity $app;

    private Context $context;

    private Manifest&Stub $manifest;

    protected function setUp(): void
    {
        $this->sourceResolver = static::createStub(SourceResolver::class);
        $this->persister = static::createStub(AppAdministrationSnippetPersister::class);
        $this->manifest = static::createStub(Manifest::class);

        $this->app = new AppEntity();
        $this->app->setId('app-id');
        $this->context = Context::createDefaultContext();
    }

    public function testGetSubscribedEvents(): void
    {
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

        $persister = $this->createMock(AppAdministrationSnippetPersister::class);
        $persister->expects($this->once())
            ->method('updateSnippets')
            ->with($this->app, [], $this->context);

        $subscriber = $this->buildSubscriber($persister);

        $event = new AppUpdatedEvent($this->app, $this->manifest, $this->context);
        $subscriber->onAppUpdate($event);
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

        $persister = $this->createMock(AppAdministrationSnippetPersister::class);
        $persister->expects($this->once())
            ->method('updateSnippets')
            ->with($this->app, $expectedSnippets, $this->context);

        $subscriber = $this->buildSubscriber($persister);

        $event = new AppUpdatedEvent($this->app, $this->manifest, $this->context);
        $subscriber->onAppUpdate($event);
    }

    private function buildSubscriber(?AppAdministrationSnippetPersister $persister = null): AppLifecycleSubscriber
    {
        return new AppLifecycleSubscriber($this->sourceResolver, $persister ?? $this->persister);
    }
}
