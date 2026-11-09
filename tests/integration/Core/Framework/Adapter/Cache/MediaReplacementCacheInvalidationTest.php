<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Test\Cms\LayoutBuilder;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Replacing the file of a media item must invalidate every cache tag of a response that renders the media.
 *
 * @internal
 */
#[Package('framework')]
class MediaReplacementCacheInvalidationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const TEST_IMAGE = __DIR__ . '/../../../Content/Media/fixtures/shopware-logo.png';

    private IdsCollection $ids;

    private Context $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();

        $this->uploadFile($this->ids->get('media'));
    }

    public function testInvalidatesProductOfProductMedia(): void
    {
        $this->createProduct(
            (new ProductBuilder($this->ids, 'product'))->price(10)->build(),
            mediaId: $this->ids->get('media'),
        );

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildProductTag($this->ids->get('product')), $tags);
    }

    public function testInvalidatesProductListingOfCategoriesOfProductMedia(): void
    {
        $this->createProduct(
            (new ProductBuilder($this->ids, 'product'))->price(10)->category('category')->build(),
            mediaId: $this->ids->get('media'),
        );

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(ProductListingRoute::buildName($this->ids->get('category')), $tags);
    }

    public function testInvalidatesProductStreamOfProductMedia(): void
    {
        $this->createProduct(
            (new ProductBuilder($this->ids, 'product'))->price(10)->build(),
            mediaId: $this->ids->get('media'),
        );

        static::getContainer()->get('product_stream.repository')->create([
            ['id' => $this->ids->get('stream'), 'name' => 'stream'],
        ], $this->context);

        // the mapping is usually written asynchronously by the product stream indexer
        static::getContainer()->get(Connection::class)->insert('product_stream_mapping', [
            'product_id' => Uuid::fromHexToBytes($this->ids->get('product')),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'product_stream_id' => Uuid::fromHexToBytes($this->ids->get('stream')),
        ]);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildStreamTag($this->ids->get('stream')), $tags);
    }

    public function testInvalidatesCategoryOfCategoryMedia(): void
    {
        static::getContainer()->get('category.repository')->create([
            ['id' => $this->ids->get('category'), 'name' => 'category', 'mediaId' => $this->ids->get('media')],
        ], $this->context);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(CategoryRoute::buildName($this->ids->get('category')), $tags);
    }

    public function testInvalidatesCmsPageOfImageElement(): void
    {
        $this->createCmsPage((new LayoutBuilder($this->ids, 'page'))->image('media')->build());

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildCmsTag($this->ids->get('page')), $tags);
    }

    public function testInvalidatesCmsPageOfImageSliderElement(): void
    {
        $this->createCmsPage((new LayoutBuilder($this->ids, 'page'))->imageSlider(['media'])->build());

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildCmsTag($this->ids->get('page')), $tags);
    }

    public function testInvalidatesCmsPageOfImageGalleryElement(): void
    {
        $this->createCmsPage((new LayoutBuilder($this->ids, 'page'))->imageGallery(['media'])->build());

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildCmsTag($this->ids->get('page')), $tags);
    }

    public function testInvalidatesCmsPageOfVideoPreviewMedia(): void
    {
        $layout = (new LayoutBuilder($this->ids, 'page'))->image('media')->build();
        $layout['sections'][0]['blocks'][0]['slots'][0] = [
            'type' => 'youtube-video',
            'slot' => 'video',
            'config' => [
                'videoID' => ['source' => 'static', 'value' => 'dQw4w9WgXcQ'],
                'previewMedia' => ['source' => 'static', 'value' => $this->ids->get('media')],
            ],
        ];
        $this->createCmsPage($layout);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildCmsTag($this->ids->get('page')), $tags);
    }

    public function testInvalidatesCmsPageOfSectionBackground(): void
    {
        $layout = (new LayoutBuilder($this->ids, 'page'))->listing()->build();
        $layout['sections'][0]['backgroundMediaId'] = $this->ids->get('media');
        $this->createCmsPage($layout);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildCmsTag($this->ids->get('page')), $tags);
    }

    public function testInvalidatesCmsPageOfBlockBackground(): void
    {
        $layout = (new LayoutBuilder($this->ids, 'page'))->listing()->build();
        $layout['sections'][0]['blocks'][0]['backgroundMediaId'] = $this->ids->get('media');
        $this->createCmsPage($layout);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildCmsTag($this->ids->get('page')), $tags);
    }

    public function testInvalidatesCategoryOfSlotConfigOverride(): void
    {
        $this->createCmsPage($this->layoutWithImageSlot());

        static::getContainer()->get('category.repository')->create([
            [
                'id' => $this->ids->get('category'),
                'name' => 'category',
                'cmsPageId' => $this->ids->get('page'),
                'slotConfig' => $this->imageSlotOverride(),
            ],
        ], $this->context);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(CategoryRoute::buildName($this->ids->get('category')), $tags);
    }

    public function testInvalidatesProductOfSlotConfigOverride(): void
    {
        $this->createCmsPage($this->layoutWithImageSlot());

        $this->createProduct([
            ...(new ProductBuilder($this->ids, 'product'))->price(10)->build(),
            'cmsPageId' => $this->ids->get('page'),
            'slotConfig' => $this->imageSlotOverride(),
        ]);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildProductTag($this->ids->get('product')), $tags);
    }

    public function testInvalidatesLandingPageOfSlotConfigOverride(): void
    {
        $this->createCmsPage($this->layoutWithImageSlot());

        static::getContainer()->get('landing_page.repository')->create([
            [
                'id' => $this->ids->get('landing-page'),
                'name' => 'landing page',
                'url' => 'landing-page',
                'salesChannels' => [['id' => TestDefaults::SALES_CHANNEL]],
                'cmsPageId' => $this->ids->get('page'),
                'slotConfig' => $this->imageSlotOverride(),
            ],
        ], $this->context);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(LandingPageRoute::buildName($this->ids->get('landing-page')), $tags);
    }

    /**
     * @return list<string>
     */
    private function replaceFileAndCollectInvalidatedTags(string $mediaId): array
    {
        $tags = [];

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            InvalidateCacheEvent::class,
            static function (InvalidateCacheEvent $event) use (&$tags): void {
                $tags = [...$tags, ...$event->getKeys()];
            }
        );

        $this->uploadFile($mediaId);

        return array_values(array_unique($tags));
    }

    private function uploadFile(string $mediaId): void
    {
        static::getContainer()->get('media.repository')->upsert([['id' => $mediaId]], $this->context);

        $filesystem = new Filesystem();
        $tempFile = $filesystem->tempnam(sys_get_temp_dir(), '');
        $filesystem->copy(self::TEST_IMAGE, $tempFile, true);

        try {
            static::getContainer()->get(FileSaver::class)->persistFileToMedia(
                new MediaFile($tempFile, 'image/png', 'png', (int) filesize($tempFile)),
                'media-' . $mediaId,
                $mediaId,
                $this->context
            );
        } finally {
            $filesystem->remove($tempFile);
        }
    }

    /**
     * @param array<string, mixed> $product
     */
    private function createProduct(array $product, ?string $mediaId = null): void
    {
        if ($mediaId !== null) {
            $product['media'] = [['id' => $this->ids->get('product-media'), 'mediaId' => $mediaId]];
            $product['coverId'] = $this->ids->get('product-media');
        }

        static::getContainer()->get('product.repository')->create([$product], $this->context);
    }

    /**
     * @param array<string, mixed> $layout
     */
    private function createCmsPage(array $layout): void
    {
        static::getContainer()->get('cms_page.repository')->create([$layout], $this->context);
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutWithImageSlot(): array
    {
        // the image element itself references another media, only the override references the replaced one
        $layout = (new LayoutBuilder($this->ids, 'page'))->image('other-media')->build();
        $layout['sections'][0]['blocks'][0]['slots'][0]['id'] = $this->ids->get('slot');

        return $layout;
    }

    /**
     * @return array<string, array<string, array{source: string, value: string}>>
     */
    private function imageSlotOverride(): array
    {
        return [
            $this->ids->get('slot') => [
                'media' => ['source' => 'static', 'value' => $this->ids->get('media')],
            ],
        ];
    }
}
