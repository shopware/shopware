<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\TemplatePersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopware\Core\Framework\App\Template\TemplateCollection;
use Shopware\Core\Framework\App\Template\TemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(TemplatePersister::class)]
class TemplatePersisterTest extends TestCase
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

    public function testCacheIsNotClearedIfInstallContext(): void
    {
        $this->cacheClearer->expects(static::never())
            ->method('clearHttpCache');

        $this->templateLoader->expects(static::once())
            ->method('getTemplatePathsForApp')
            ->with($this->manifest)
            ->willReturn(['/path/1']);

        $this->templateLoader->expects(static::once())
            ->method('getTemplateContent')
            ->with('/path/1')
            ->willReturn('content1');

        $persister = $this->buildPersister(['/path/1' => 'content1']);
        $persister->updateTemplates($this->manifest, $this->ids->get('app1'), Context::createDefaultContext(), true);
    }

    public function testCacheIsNotClearedIfNoTemplates(): void
    {
        $this->cacheClearer->expects(static::never())
            ->method('clearHttpCache');

        $this->templateLoader->expects(static::once())
            ->method('getTemplatePathsForApp')
            ->with($this->manifest)
            ->willReturn([]);

        $persister = $this->buildPersister([]);
        $persister->updateTemplates($this->manifest, $this->ids->get('app1'), Context::createDefaultContext(), false);
    }

    public function testCacheIsNotClearedIfTemplatesAreNotChanged(): void
    {
        $this->cacheClearer->expects(static::never())
            ->method('clearHttpCache');

        $this->templateLoader->expects(static::once())
            ->method('getTemplatePathsForApp')
            ->with($this->manifest)
            ->willReturn(['/path/1']);

        $this->templateLoader->expects(static::once())
            ->method('getTemplateContent')
            ->with('/path/1')
            ->willReturn('content1');

        $persister = $this->buildPersister(['/path/1' => 'content1']);
        $persister->updateTemplates($this->manifest, $this->ids->get('app1'), Context::createDefaultContext(), false);
    }

    public function testCacheIsClearedIfTemplatesChanged(): void
    {
        $this->cacheClearer->expects(static::once())
            ->method('clearHttpCache');

        $this->templateLoader->expects(static::once())
            ->method('getTemplatePathsForApp')
            ->with($this->manifest)
            ->willReturn(['/path/1']);

        $this->templateLoader->expects(static::once())
            ->method('getTemplateContent')
            ->with('/path/1')
            ->willReturn('content2');

        $persister = $this->buildPersister(['/path/1' => 'content1']);
        $persister->updateTemplates($this->manifest, $this->ids->get('app1'), Context::createDefaultContext(), false);
    }

    public function testCacheIsClearedIfTemplateRemoved(): void
    {
        $this->cacheClearer->expects(static::once())
            ->method('clearHttpCache');

        $this->templateLoader->expects(static::once())
            ->method('getTemplatePathsForApp')
            ->with($this->manifest)
            ->willReturn(['/path/1']);

        $this->templateLoader->expects(static::once())
            ->method('getTemplateContent')
            ->with('/path/1')
            ->willReturn('content1');

        $persister = $this->buildPersister(['/path/1' => 'content1', '/path/2' => 'content2']);
        $persister->updateTemplates($this->manifest, $this->ids->get('app1'), Context::createDefaultContext(), false);
    }

    /**
     * @param array<string, string> $templates
     */
    private function buildPersister(array $templates): TemplatePersister
    {
        return new TemplatePersister(
            $this->templateLoader,
            $this->templateRepository,
            $this->buildAppRepository($templates),
            $this->cacheClearer
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
}
