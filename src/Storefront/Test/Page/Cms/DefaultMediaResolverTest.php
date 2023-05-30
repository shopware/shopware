<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Cms;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Cms\DefaultMediaResolver;

/**
 * @internal
 */
#[Package('content')]
class DefaultMediaResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURES_DIRECTORY = '/fixtures';

    private DefaultMediaResolver $mediaResolver;

    private MockObject&AbstractDefaultMediaResolver $decorated;

    protected function setUp(): void
    {
        $packages = $this->getContainer()->get('assets.packages');

        $translator = $this->createConfiguredMock(
            Translator::class,
            [
                'trans' => 'foobar',
            ]
        );

        $this->decorated = $this->createMock(AbstractDefaultMediaResolver::class);
        $this->mediaResolver = new DefaultMediaResolver($this->decorated, $translator, $packages);
    }

    public function testGetDefaultMediaEntityWithoutValidFileName(): void
    {
        $this->decorated->method('getDefaultCmsMediaEntity')->willReturn(null);
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('this/file/does/not/exists');

        static::assertNull($media);
    }

    public function testGetDefaultMediaEntityWithValidFileName(): void
    {
        $this->decorated->method('getDefaultCmsMediaEntity')->willReturn(new MediaEntity());
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('storefront/assets/default/cms/shopware.jpg');

        static::assertInstanceOf(MediaEntity::class, $media);

        // ensure url and translations are set correctly
        static::assertStringContainsString('bundles/storefront/assets/default/cms/shopware.jpg', $media->getUrl());
        static::assertEquals('foobar', $media->getTranslated()['title']);
        static::assertEquals('foobar', $media->getTranslated()['alt']);
    }
}
