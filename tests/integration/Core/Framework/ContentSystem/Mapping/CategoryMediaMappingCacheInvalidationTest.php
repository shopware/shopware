<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestKernel;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[Group('cache')]
class CategoryMediaMappingCacheInvalidationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private CacheStore $store;

    private CacheInvalidator $invalidator;

    private string $mediaId;

    private string $categoryId;

    private string $otherCategoryId;

    private string $textCategoryId;

    public static function tearDownAfterClass(): void
    {
        KernelLifecycleManager::ensureKernelShutdown();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        KernelLifecycleManager::bootKernel();
        $this->browser = $this->createSalesChannelBrowser();
        $this->store = static::getContainer()->get(CacheStore::class);
        $this->invalidator = static::getContainer()->get(CacheInvalidator::class);
        $this->mediaId = Uuid::randomHex();
        $otherMediaId = Uuid::randomHex();
        $this->categoryId = Uuid::randomHex();
        $this->otherCategoryId = Uuid::randomHex();
        $this->textCategoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        static::getContainer()->get('media.repository')->create(array_map(static fn (string $id): array => [
            'id' => $id, 'fileName' => 'original', 'fileExtension' => 'png', 'mimeType' => 'image/png',
            'path' => 'media/' . $id . '.png', 'private' => false, 'alt' => 'Original alt', 'title' => 'Original title',
        ], [$this->mediaId, $otherMediaId]), $context);
        static::getContainer()->get('category.repository')->create([
            ['id' => $this->categoryId, 'name' => 'Mapped category', 'active' => true, 'mediaId' => $this->mediaId],
            ['id' => $this->otherCategoryId, 'name' => 'Other media', 'active' => true, 'mediaId' => $otherMediaId],
            ['id' => $this->textCategoryId, 'name' => 'Text-only category', 'active' => true, 'mediaId' => $this->mediaId],
        ], $context);
        $imageLayoutId = $this->createLayout([
            'id' => Uuid::randomHex(), 'component' => 'Sw:Media:Image',
            'dataRequirements' => ['media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]],
            'acceptsContext' => ['media' => ['type' => 'single', 'required' => false, 'scope' => 'root', 'source' => [
                'type' => 'root', 'id' => 'category', 'path' => 'media',
            ]]],
        ]);
        $textLayoutId = $this->createLayout([
            'id' => Uuid::randomHex(), 'component' => 'Sw:Content:Text',
            'properties' => ['text' => '<p>{{map:category.name}}</p>'],
        ]);
        static::getContainer()->get('category_content_layout.repository')->create([
            ['id' => Uuid::randomHex(), 'categoryId' => $this->categoryId, 'contentLayoutId' => $imageLayoutId],
            ['id' => Uuid::randomHex(), 'categoryId' => $this->otherCategoryId, 'contentLayoutId' => $imageLayoutId],
            ['id' => Uuid::randomHex(), 'categoryId' => $this->textCategoryId, 'contentLayoutId' => $textLayoutId],
        ], $context);
        $this->invalidator->invalidateExpired();
    }

    #[DataProvider('mediaChanges')]
    public function testMediaEditsRefreshMappedImagesAndKeepUnrelatedPages(string $entity, string $field, string $before, string $after): void
    {
        $mapped = $this->cachePage($this->categoryId);
        $other = $this->cachePage($this->otherCategoryId);
        $text = $this->cachePage($this->textCategoryId);
        static::assertSame(str_replace('{id}', $this->mediaId, $before), $this->cachedMedia($mapped)[$field]);
        $otherContent = $this->store->lookup($other)?->getContent();
        $textContent = $this->store->lookup($text)?->getContent();
        static::assertIsString($otherContent);
        static::assertIsString($textContent);

        $primaryKey = $entity === 'media_translation'
            ? ['mediaId' => $this->mediaId, 'languageId' => Defaults::LANGUAGE_SYSTEM]
            : ['id' => $this->mediaId];
        $repository = static::getContainer()->get($entity . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);
        $repository->update([[
            ...$primaryKey, $field => $after,
        ]], Context::createDefaultContext());
        $this->invalidator->invalidateExpired();

        static::assertNull($this->store->lookup($mapped), 'A media edit must evict the mapped page.');
        static::assertSame($otherContent, $this->store->lookup($other)?->getContent());
        static::assertSame($textContent, $this->store->lookup($text)?->getContent());
        static::assertSame($after, $this->cachedMedia($this->cachePage($this->categoryId))[$field]);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function mediaChanges(): iterable
    {
        yield 'translated alt through media' => ['media', 'alt', 'Original alt', 'Updated alt'];
        yield 'direct translation title write' => ['media_translation', 'title', 'Original title', 'Updated title'];
        yield 'replacement file name' => ['media', 'fileName', 'original', 'replacement'];
        yield 'replacement file path' => ['media', 'path', 'media/{id}.png', 'media/replacement.png'];
    }

    public function testDeletingMediaEvictsItsMappedPage(): void
    {
        $mapped = $this->cachePage($this->categoryId);
        $other = $this->cachePage($this->otherCategoryId);
        static::assertSame($this->mediaId, $this->cachedMedia($mapped)['id']);
        static::assertNotNull($this->store->lookup($other));

        static::getContainer()->get('media.repository')->delete([['id' => $this->mediaId]], Context::createDefaultContext());
        $this->invalidator->invalidateExpired();

        static::assertNull($this->store->lookup($mapped));
        static::assertNotNull($this->store->lookup($other));
    }

    public function testMediaEditsInvalidateWithRemoteThumbnailsEnabled(): void
    {
        $previousKernelClass = KernelFactory::$kernelClass;
        KernelFactory::$kernelClass = MappedMediaRemoteThumbnailTestKernel::class;
        try {
            KernelLifecycleManager::bootKernel(cacheId: 'mapped-media-remote-thumbnails');
        } finally {
            KernelFactory::$kernelClass = $previousKernelClass;
        }
        static::assertTrue(static::getContainer()->getParameter('shopware.media.remote_thumbnails.enable'));
        $this->browser = $this->createSalesChannelBrowser();
        $this->store = static::getContainer()->get(CacheStore::class);
        $this->invalidator = static::getContainer()->get(CacheInvalidator::class);
        $mapped = $this->cachePage($this->categoryId);
        static::assertSame('Original alt', $this->cachedMedia($mapped)['alt']);

        static::getContainer()->get('media.repository')->update([['id' => $this->mediaId, 'alt' => 'Remote update']], Context::createDefaultContext());
        $this->invalidator->invalidateExpired();

        static::assertNull($this->store->lookup($mapped));
        static::assertSame('Remote update', $this->cachedMedia($this->cachePage($this->categoryId))['alt']);
    }

    public function testMediaIndexingInvalidatesOnlyTheReferencedImage(): void
    {
        $mapped = $this->cachePage($this->categoryId);
        $other = $this->cachePage($this->otherCategoryId);

        static::getContainer()->get(EventDispatcherInterface::class)->dispatch(new MediaIndexerEvent([$this->mediaId], Context::createDefaultContext()));
        $this->invalidator->invalidateExpired();

        static::assertNull($this->store->lookup($mapped));
        static::assertNotNull($this->store->lookup($other));
    }

    /**
     * @param array<string, mixed> $element
     */
    private function createLayout(array $element): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('content_layout.repository')->create([[
            'id' => $id, 'name' => 'Media dependency ' . $id, 'version' => '1.0.0', 'rootSource' => 'category',
            'layout' => [['id' => Uuid::randomHex(), 'component' => 'Sw:Grid:Container', 'slots' => ['content' => [$element]]]],
        ]], Context::createDefaultContext());

        return $id;
    }

    private function cachePage(string $categoryId): Request
    {
        $path = '/store-api/content/category/' . $categoryId;
        $this->browser->request('GET', $path);
        $served = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $served->getStatusCode(), (string) $served->getContent());
        $tags = static::getContainer()->get(CacheTagCollector::class)->get(Request::create($path));
        $request = Request::create('http://localhost/category-media-cache/' . $categoryId);
        $response = new Response((string) $served->getContent());
        $response->setPublic()->setMaxAge(3600)->setSharedMaxAge(3600);
        $response->setDate(new \DateTimeImmutable('-2 seconds'));
        $response->headers->addCacheControlDirective('stale-while-revalidate', '1');
        $response->headers->set(CacheStore::TAG_HEADER, json_encode($tags, \JSON_THROW_ON_ERROR));
        $this->store->write($request, $response);
        static::assertNotNull($this->store->lookup($request), 'The rendered page must be present in the HTTP cache.');

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function cachedMedia(Request $request): array
    {
        $response = $this->store->lookup($request);
        static::assertNotNull($response);
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['elements'][0]['slots']['content'][0]['properties']['media'];
    }
}

/**
 * @internal
 */
class MappedMediaRemoteThumbnailTestKernel extends TestKernel
{
    public function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);
        $container->loadFromExtension('shopware', ['media' => ['remote_thumbnails' => ['enable' => true]]]);
    }
}
