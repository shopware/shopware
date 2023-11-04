<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\BinaryType;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Test\EntityFixturesBase;

trait MediaFixtures
{
    use EntityFixturesBase;

    /**
     * @var array<string, array<string, mixed>>
     */
    public $mediaFixtures;

    /**
     * @before
     */
    public function initializeMediaFixtures(): void
    {
        $thumbnailSize150Id = Uuid::randomHex();
        $thumbnailSize300Id = Uuid::randomHex();

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
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimeTxtEtxTxt' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'plain/txt',
                'fileExtension' => 'txt',
                'fileName' => 'textFileWithExtension',
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
                                'id' => $thumbnailSize150Id,
                                'width' => 150,
                                'height' => 150,
                            ],
                            [
                                'id' => $thumbnailSize300Id,
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
                                'id' => $thumbnailSize150Id,
                                'width' => 150,
                                'height' => 150,
                            ],
                            [
                                'id' => $thumbnailSize300Id,
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
                                'id' => $thumbnailSize150Id,
                                'width' => 1500,
                                'height' => 1500,
                            ],
                            [
                                'id' => $thumbnailSize300Id,
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
