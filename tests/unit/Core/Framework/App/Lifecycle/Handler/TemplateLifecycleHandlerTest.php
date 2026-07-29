<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\TemplateLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopware\Core\Framework\App\Template\TemplateCollection;
use Shopware\Core\Framework\App\Template\TemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(TemplateLifecycleHandler::class)]
class TemplateLifecycleHandlerTest extends TestCase
{
    private readonly CacheClearer&MockObject $cacheClearer;

    private readonly AbstractTemplateLoader&MockObject $templateLoader;

    /**
     * @var StaticEntityRepository<TemplateCollection>
     */
    private readonly StaticEntityRepository $templateRepository;

    private readonly Manifest&MockObject $manifest;

    private readonly IdsCollection $ids;

    protected function setUp(): void
    {
        $this->templateLoader = $this->createMock(AbstractTemplateLoader::class);
        $this->templateRepository = new StaticEntityRepository([]);
        $this->manifest = $this->createMock(Manifest::class);
        $this->cacheClearer = $this->createMock(CacheClearer::class);
        $this->ids = new IdsCollection();
    }

    public function testCacheIsNotClearedOnInstall(): void
    {
        $this->cacheClearer->expects($this->never())
            ->method('clearHttpCache');

        $this->templateLoader->expects($this->once())
            ->method('getTemplatePathsForApp')
            ->with($this->manifest)
            ->willReturn(['/path/1']);

        $this->templateLoader->expects($this->once())
            ->method('getTemplateContent')
            ->with('/path/1')
            ->willReturn('content1');

        $persister = $this->buildHandler(['/path/1' => 'content1']);
        $persister->install($this->buildContext());
    }

    public function testCacheIsNotClearedIfNoTemplates(): void
    {
        $this->cacheClearer->expects($this->never())
            ->method('clearHttpCache');

        $this->templateLoader->expects($this->once())
            ->method('getTemplatePathsForApp')
            ->with($this->manifest)
            ->willReturn([]);

        $persister = $this->buildHandler([]);
        $persister->update($this->buildContext());
    }

    public function testCacheIsNotClearedIfTemplatesAreNotChanged(): void
    {
        $this->cacheClearer->expects($this->never())
            ->method('clearHttpCache');

        $this->templateLoader->expects($this->once())
            ->method('getTemplatePathsForApp')
            ->with($this->manifest)
            ->willReturn(['/path/1']);

        $this->templateLoader->expects($this->once())
            ->method('getTemplateContent')
            ->with('/path/1')
            ->willReturn('content1');

        $persister = $this->buildHandler(['/path/1' => 'content1']);
        $persister->update($this->buildContext());
    }

    public function testCacheIsClearedIfTemplatesChanged(): void
    {
        $this->cacheClearer->expects($this->once())
            ->method('clearHttpCache');

        $this->templateLoader->expects($this->once())
            ->method('getTemplatePathsForApp')
            ->with($this->manifest)
            ->willReturn(['/path/1']);

        $this->templateLoader->expects($this->once())
            ->method('getTemplateContent')
            ->with('/path/1')
            ->willReturn('content2');

        $persister = $this->buildHandler(['/path/1' => 'content1']);
        $persister->update($this->buildContext());
    }

    public function testCacheIsClearedIfTemplateRemoved(): void
    {
        $this->cacheClearer->expects($this->once())
            ->method('clearHttpCache');

        $this->templateLoader->expects($this->once())
            ->method('getTemplatePathsForApp')
            ->with($this->manifest)
            ->willReturn(['/path/1']);

        $this->templateLoader->expects($this->once())
            ->method('getTemplateContent')
            ->with('/path/1')
            ->willReturn('content1');

        $persister = $this->buildHandler(['/path/1' => 'content1', '/path/2' => 'content2']);
        $persister->update($this->buildContext());
    }

    public function testActivateUpdatesInactiveTemplates(): void
    {
        $appId = $this->ids->get('app1');
        $templateIds = [$this->ids->get('template1'), $this->ids->get('template2')];
        $this->templateRepository->addSearch($templateIds);
        $this->cacheClearer->expects($this->once())->method('clearHttpCache');

        $this->buildHandler([])->activate(new AppActivationContext($this->buildApp($appId), Context::createDefaultContext()));

        static::assertSame([
            ['id' => $templateIds[0], 'active' => true],
            ['id' => $templateIds[1], 'active' => true],
        ], $this->templateRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesActiveTemplates(): void
    {
        $appId = $this->ids->get('app1');
        $templateIds = [$this->ids->get('template1'), $this->ids->get('template2')];
        $this->templateRepository->addSearch($templateIds);
        $this->cacheClearer->expects($this->once())->method('clearHttpCache');

        $this->buildHandler([])->deactivate(new AppActivationContext($this->buildApp($appId), Context::createDefaultContext()));

        static::assertSame([
            ['id' => $templateIds[0], 'active' => false],
            ['id' => $templateIds[1], 'active' => false],
        ], $this->templateRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    /**
     * @param array<string, string> $templates
     */
    private function buildHandler(array $templates): TemplateLifecycleHandler
    {
        return new TemplateLifecycleHandler(
            $this->templateLoader,
            $this->templateRepository,
            $this->buildAppRepository($templates),
            $this->cacheClearer
        );
    }

    private function buildContext(): AppPersistContext
    {
        $app = $this->buildApp($this->ids->get('app1'));
        $app->setActive(true);

        return new AppPersistContext(
            manifest: $this->manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(),
            defaultLocale: 'en-GB',
        );
    }

    /**
     * @param array<string, string> $templates
     *
     * @return StaticEntityRepository<AppCollection>
     */
    private function buildAppRepository(array $templates): StaticEntityRepository
    {
        $app = new AppEntity();
        $app->setId($this->ids->create('app1'));
        $app->setTemplates(new TemplateCollection(array_map(function (string $path, string $content): TemplateEntity {
            $t = new TemplateEntity();
            $t->setId($this->ids->create($path));
            $t->setPath($path);
            $t->setTemplate($content);
            $t->setHash(Hasher::hash($content));

            return $t;
        }, array_keys($templates), $templates)));
        $app->setActive(true);

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([new AppCollection([$app])]);

        return $repo;
    }

    private function buildApp(string $appId): AppEntity
    {
        $app = new AppEntity();
        $app->setId($appId);

        return $app;
    }
}
