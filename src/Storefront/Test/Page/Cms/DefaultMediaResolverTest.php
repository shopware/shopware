<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Cms\DefaultMediaResolver;

class DefaultMediaResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURES_DIRECTORY = '/fixtures';

    private DefaultMediaResolver $mediaResolver;

    public function setUp(): void
    {
        $translator = $this->createConfiguredMock(Translator::class, ['trans' => 'foobar']);
        $assetExtension = $this->getContainer()->get('twig.extension.assets');

        $this->mediaResolver = new DefaultMediaResolver(__DIR__ . self::FIXTURES_DIRECTORY, $translator, $assetExtension);
    }

    public function testGetDefaultMediaEntityWithoutValidFileName(): void
    {
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('this/file/does/not/exists');

        static::assertNull($media);
    }

    public function testGetDefaultMediaEntityWithValidFileName(): void
    {
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('shopware.jpg');

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertEquals('shopware.jpg', $media->getFileName());
        static::assertEquals('image/jpeg', $media->getMimeType());
        static::assertEquals('jpg', $media->getFileExtension());

        static::assertStringContainsString('bundles/storefront/assets/default/cms/shopware.jpg', $media->getUrl());
        static::assertEquals('foobar', $media->getTranslated()['title']);
        static::assertEquals('foobar', $media->getTranslated()['alt']);
    }
}
