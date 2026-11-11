<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\CmsElementMediaFieldProvider;
use Shopware\Core\Content\Media\Cms\ImageCmsElementResolver;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\CmsSlotMediaIdResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsSlotMediaIdResolver::class)]
class CmsSlotMediaIdResolverTest extends TestCase
{
    private CmsSlotMediaIdResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CmsSlotMediaIdResolver([new CmsElementMediaFieldProvider(new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class)), ['media'])]);
    }

    public function testAsksTheProviderOfTheElementForItsMedia(): void
    {
        $mediaId = Uuid::randomHex();

        $mediaIds = $this->resolver->getMediaIds('image', ['media' => ['source' => 'static', 'value' => $mediaId]]);

        static::assertSame([$mediaId], $mediaIds);
    }

    public function testCombinesTheMediaOfAllProvidersOfTheElement(): void
    {
        $mediaId = Uuid::randomHex();
        $previewId = Uuid::randomHex();
        $resolver = new CmsSlotMediaIdResolver([
            new CmsElementMediaFieldProvider(new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class)), ['media']),
            new CmsElementMediaFieldProvider(new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class)), ['previewMedia']),
        ]);

        $mediaIds = $resolver->getMediaIds('image', [
            'media' => ['source' => 'static', 'value' => $mediaId],
            'previewMedia' => ['source' => 'static', 'value' => $previewId],
        ]);

        static::assertSame([$mediaId, $previewId], $mediaIds);
    }

    public function testReturnsNothingForElementsWithoutProvider(): void
    {
        static::assertSame([], $this->resolver->getMediaIds('unknown', ['media' => ['source' => 'static', 'value' => Uuid::randomHex()]]));
    }
}
