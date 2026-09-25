<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class MediaImageGridComponentTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testElementTypeBindsTheMediaCollectionLoader(): void
    {
        $registry = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

        $specification = $registry->all()['core:Sw:Media:ImageGrid'] ?? null;
        static::assertInstanceOf(BindingSpecification::class, $specification);

        $binding = $specification->resolves()['mediaItems'] ?? null;
        static::assertInstanceOf(LoaderBinding::class, $binding);
        static::assertSame('entity_collection', $binding->loader);
        static::assertSame('mediaIds', $binding->config['property'] ?? null);
    }

    public function testOnlyTheFirstItemSpansTheFullWidth(): void
    {
        $html = $this->render(['mediaItems' => $this->mediaCollection('first.jpg', 'second.jpg', 'third.jpg')]);

        static::assertSame(3, substr_count($html, '<li class="sw-media-image-grid__item'));
        static::assertSame(1, substr_count($html, 'is--featured'));
        static::assertMatchesRegularExpression('/sw-media-image-grid__item is--featured">\s*<button[^>]*data-media-id="1"/', $html);
    }

    public function testColumnsAreExposedAsCssVariable(): void
    {
        $html = $this->render([
            'mediaItems' => $this->mediaCollection('first.jpg', 'second.jpg'),
            'columns' => 3,
        ]);

        static::assertStringContainsString('--sw-media-image-grid-columns: 3;', $html);
    }

    public function testFeaturedItemSpansOnlyTheConfiguredColumnsWhenFewerThanTheGrid(): void
    {
        $html = $this->render([
            'mediaItems' => $this->mediaCollection('first.jpg', 'second.jpg'),
            'columns' => 3,
            'featuredColumns' => 2,
        ]);

        static::assertStringContainsString('--sw-media-image-grid-featured-columns: 2;', $html);
        static::assertSame(1, substr_count($html, 'is--featured-partial'));
    }

    /**
     * A span wider than the grid would make CSS grid add implicit columns instead of spanning the row.
     */
    public function testFeaturedItemSpansTheFullWidthWhenItsColumnsReachTheGrid(): void
    {
        $html = $this->render([
            'mediaItems' => $this->mediaCollection('first.jpg', 'second.jpg'),
            'columns' => 2,
            'featuredColumns' => 4,
        ]);

        static::assertStringNotContainsString('--sw-media-image-grid-featured-columns', $html);
        static::assertStringNotContainsString('is--featured-partial', $html);
        static::assertSame(1, substr_count($html, 'is--featured'));
    }

    /**
     * The lightbox gallery scrolls to the item whose `data-media-id` triggered the modal, so every
     * tile must point at the one lightbox and carry its 1-based position.
     */
    public function testEveryItemOpensTheSharedLightboxAtItsOwnPosition(): void
    {
        $html = $this->render(['mediaItems' => $this->mediaCollection('first.jpg', 'second.jpg', 'third.jpg')]);

        static::assertSame(1, preg_match('/data-bs-target="#(sw-media-image-grid-lightbox-[^"]+)"/', $html, $matches));
        $lightboxId = $matches[1];

        static::assertSame(3, substr_count($html, 'data-bs-toggle="modal"'));
        static::assertSame(3, substr_count($html, 'data-bs-target="#' . $lightboxId . '"'));
        static::assertStringContainsString('id="' . $lightboxId . '"', $html);

        // The grid's three triggers plus the three preview items of the lightbox gallery.
        foreach (['1', '2', '3'] as $position) {
            static::assertSame(2, substr_count($html, 'data-media-id="' . $position . '"'));
        }

        static::assertStringContainsString('data-component="Sw:Media:Gallery"', $html);
    }

    public function testDisabledFullScreenGalleryRendersNoInteractiveMarkup(): void
    {
        $html = $this->render([
            'mediaItems' => $this->mediaCollection('first.jpg', 'second.jpg'),
            'showFullScreenGallery' => false,
        ]);

        static::assertSame(2, substr_count($html, '<li class="sw-media-image-grid__item'));
        static::assertStringNotContainsString('<button', $html);
        static::assertStringNotContainsString('data-bs-toggle', $html);
        static::assertStringNotContainsString('sw-media-gallery-lightbox', $html);
    }

    public function testRendersAPlaceholderWithoutMedia(): void
    {
        $html = $this->render(['mediaItems' => new MediaCollection()]);

        static::assertStringNotContainsString('sw-media-image-grid', $html);
        static::assertStringContainsString('sw-media-image__placeholder', $html);
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(array $props): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig
            ->createTemplate('{{ component(\'Sw:Media:ImageGrid\', props) }}')
            ->render(['props' => $props]);
    }

    private function mediaCollection(string ...$fileNames): MediaCollection
    {
        $collection = new MediaCollection();

        foreach ($fileNames as $fileName) {
            $media = new MediaEntity();
            $media->setId(Uuid::randomHex());
            $media->setUrl('https://shopware.local/media/' . $fileName);
            $media->setPath('media/' . $fileName);
            $media->setMediaType(new ImageType());
            $media->setTranslated(['alt' => $fileName, 'title' => null]);

            $collection->add($media);
        }

        return $collection;
    }
}
