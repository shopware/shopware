<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use PHPUnit\Framework\Attributes\Before;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\BinaryType;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Traits\EntityFixturesBase;

/**
 * @internal
 */
trait MediaFixtures
{
    use EntityFixturesBase;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $mediaFixtures;

    public string $thumbnailSize150Id;

    public string $thumbnailSize200Id;

    public string $thumbnailSize300Id;

    #[Before]
    public function initializeMediaFixtures(): void
    {
        $this->thumbnailSize150Id = Uuid::randomHex();
        $this->thumbnailSize300Id = Uuid::randomHex();

        // Media thumbnail size 200 is a default size which already exists in the database, to prevent duplicate entries we use the existing one.
        /** @var EntityRepository<MediaThumbnailSizeCollection> */
        $mediaThumbnailSizeRepository = static::getFixtureRepository('media_thumbnail_size');
        $mediaThumbnailSizes = $mediaThumbnailSizeRepository->search(new Criteria(), $this->entityFixtureContext)->getEntities();
        $this->thumbnailSize200Id = $mediaThumbnailSizes->filter(
            static fn (MediaThumbnailSizeEntity $size) => $size->getWidth() === 200 && $size->getHeight() === 200
        )->first()?->getId() ?? Uuid::randomHex();

        $this->mediaFixtures = [
            'NamedEmpty' => [
                'id' => Uuid::randomHex(),
            ],
            'NamedMimePng' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/png',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimePngEtxPng' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithExtension',
                'path' => 'media/_test/pngFileWithExtension.png',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimeTxtEtxTxt' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'plain/txt',
                'fileExtension' => 'txt',
                'fileName' => 'textFileWithExtension',
                'path' => 'media/_test/textFileWithExtension.txt',
                'fileSize' => 1024,
                'mediaType' => new BinaryType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimeJpgEtxJpg' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileName' => 'jpgFileWithExtension',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimePdfEtxPdf' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'application/pdf',
                'fileExtension' => 'pdf',
                'fileName' => 'pdfFileWithExtension',
                'fileSize' => 1024,
                'mediaType' => new DocumentType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedWithThumbnail' => [
                'id' => Uuid::randomHex(),
                'thumbnails' => [
                    [
                        'width' => 200,
                        'height' => 200,
                        'highDpi' => false,
                        'mediaThumbnailSizeId' => $this->thumbnailSize200Id,
                    ],
                ],
            ],
            'MediaWithProduct' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithProduct',
                'productMedia' => [
                    [
                        'id' => Uuid::randomHex(),
                        'product' => [
                            'id' => Uuid::randomHex(),
                            'productNumber' => Uuid::randomHex(),
                            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                            'stock' => 10,
                            'manufacturer' => [
                                'name' => 'test',
                            ],
                            'name' => 'product',
                            'tax' => [
                                'taxRate' => 13,
                                'name' => 'green',
                            ],
                        ],
                    ],
                ],
            ],
            'MediaWithManufacturer' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithManufacturer',
                'productManufacturers' => [
                    [
                        'id' => Uuid::randomHex(),
                        'name' => 'manufacturer',
                    ],
                ],
            ],
            'NamedMimePngEtxPngWithFolder' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithExtensionAndFolder',
                'fileSize' => 1024,
                'path' => 'media/_test/pngFileWithExtensionAndFolder.png',
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                'mediaFolder' => [
                    'name' => 'test folder',
                    'useParentConfiguration' => false,
                    'configuration' => [
                        'createThumbnails' => true,
                        'keepAspectRatio' => true,
                        'thumbnailQuality' => 80,
                        'mediaThumbnailSizes' => [
                            [
                                'id' => $this->thumbnailSize150Id,
                                'width' => 150,
                                'height' => 150,
                            ],
                            [
                                'id' => $this->thumbnailSize300Id,
                                'width' => 300,
                                'height' => 300,
                            ],
                        ],
                    ],
                ],
            ],
            'NamedMimeJpgEtxJpgWithFolder' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileName' => 'jpgFileWithExtensionAndFolder',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                'mediaFolder' => [
                    'name' => 'test folder',
                    'useParentConfiguration' => false,
                    'configuration' => [
                        'createThumbnails' => true,
                        'keepAspectRatio' => true,
                        'thumbnailQuality' => 80,
                        'mediaThumbnailSizes' => [
                            [
                                'id' => $this->thumbnailSize150Id,
                                'width' => 150,
                                'height' => 150,
                            ],
                            [
                                'id' => $this->thumbnailSize300Id,
                                'width' => 300,
                                'height' => 300,
                            ],
                        ],
                    ],
                ],
            ],
            'NamedMimeJpgEtxJpgWithFolderWithoutThumbnails' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileName' => 'jpgFileWithExtensionAndCatalog',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                'mediaFolder' => [
                    'name' => 'test folder',
                    'useParentConfiguration' => false,
                    'configuration' => [
                        'createThumbnails' => false,
                    ],
                ],
            ],
            'NamedMimePngEtxPngWithFolderHugeThumbnails' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithExtensionAndFolder',
                'fileSize' => 1024,
                'path' => 'media/_test/pngFileWithExtensionAndFolder.png',
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                'mediaFolder' => [
                    'name' => 'test folder',
                    'useParentConfiguration' => false,
                    'configuration' => [
                        'createThumbnails' => true,
                        'keepAspectRatio' => true,
                        'thumbnailQuality' => 80,
                        'mediaThumbnailSizes' => [
                            [
                                'id' => $this->thumbnailSize150Id,
                                'width' => 1500,
                                'height' => 1500,
                            ],
                            [
                                'id' => $this->thumbnailSize300Id,
                                'width' => 3000,
                                'height' => 3000,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getEmptyMedia(): MediaEntity
    {
        return $this->getMediaFixture('NamedEmpty');
    }

    public function getPngWithoutExtension(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimePng');
    }

    public function getPng(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimePngEtxPng');
    }

    public function getTxt(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimeTxtEtxTxt');
    }

    public function getJpg(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimeJpgEtxJpg');
    }

    public function getPdf(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimePdfEtxPdf');
    }

    public function getMediaWithThumbnail(): MediaEntity
    {
        return $this->getMediaFixture('NamedWithThumbnail');
    }

    public function getMediaWithProduct(): MediaEntity
    {
        return $this->getMediaFixture('MediaWithProduct');
    }

    public function getMediaWithManufacturer(): MediaEntity
    {
        return $this->getMediaFixture('MediaWithManufacturer');
    }

    public function getPngWithFolder(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimePngEtxPngWithFolder');
    }

    public function getPngWithFolderHugeThumbnails(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimePngEtxPngWithFolderHugeThumbnails');
    }

    public function getJpgWithFolder(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimeJpgEtxJpgWithFolder');
    }

    public function getJpgWithFolderWithoutThumbnails(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimeJpgEtxJpgWithFolderWithoutThumbnails');
    }

    private function getMediaFixture(string $fixtureName): MediaEntity
    {
        /** @var MediaEntity $media */
        $media = $this->createFixture(
            $fixtureName,
            $this->mediaFixtures,
            self::getFixtureRepository('media')
        );

        return $media;
    }
}
