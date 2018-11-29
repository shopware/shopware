<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\MediaType\BinaryType;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Test\EntityFixturesBase;

trait MediaFixtures
{
    use EntityFixturesBase;

    /**
     * @var array
     */
    public $mediaFixtures;

    /**
     * @before
     */
    public function initializeMediaFixtures(): void
    {
        $mediaId = Uuid::uuid4()->getHex();

        $this->mediaFixtures = [
            'NamedEmpty' => [
                'id' => Uuid::uuid4()->getHex(),
            ],
            'NamedMimePng' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'image/png',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimePngEtxPng' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithExtension',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],

            'NamedMimeTxtEtxTxt' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'plain/txt',
                'fileExtension' => 'txt',
                'fileName' => 'textFileWithExtension',
                'fileSize' => 1024,
                'mediaType' => new BinaryType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimeJpgEtxJpg' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileName' => 'jpgFileWithExtensionAndCatalog',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimePdfEtxPdf' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'application/pdf',
                'fileExtension' => 'pdf',
                'fileName' => 'pdfFileWithExtensionAndCatalog',
                'fileSize' => 1024,
                'mediaType' => new DocumentType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedWithThumbnail' => [
                'id' => Uuid::uuid4()->getHex(),
                'thumbnails' => [
                    [
                        'width' => 200,
                        'height' => 200,
                        'highDpi' => false,
                    ],
                ],
            ],
            'MediaWithProduct' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithProduct',
                'productMedia' => [
                    [
                        'id' => Uuid::uuid4()->getHex(),
                        'product' => [
                            'id' => Uuid::uuid4()->getHex(),
                            'price' => ['gross' => 10, 'net' => 9],
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
                'id' => $mediaId,
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithManufacturer',
                'productManufacturers' => [
                    [
                        'id' => Uuid::uuid4()->getHex(),
                        'name' => 'manufacturer',
                        'mediaId' => $mediaId,
                    ],
                ],
            ],
        ];
    }

    public function getContextWithWriteAccess(): Context
    {
        $context = Context::createDefaultContext();
        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        return $context;
    }

    public function getEmptyMedia(): MediaStruct
    {
        return $this->getMediaFixture('NamedEmpty');
    }

    public function getPngWithoutExtension(): MediaStruct
    {
        return $this->getMediaFixture('NamedMimePng');
    }

    public function getPng(): MediaStruct
    {
        return $this->getMediaFixture('NamedMimePngEtxPng');
    }

    public function getTxt(): MediaStruct
    {
        return $this->getMediaFixture('NamedMimeTxtEtxTxt');
    }

    public function getJpg(): MediaStruct
    {
        return $this->getMediaFixture('NamedMimeJpgEtxJpg');
    }

    public function getPdf(): MediaStruct
    {
        return $this->getMediaFixture('NamedMimePdfEtxPdf');
    }

    public function getMediaWithThumbnail(): MediaStruct
    {
        return $this->getMediaFixture('NamedWithThumbnail');
    }

    public function getMediaWithProduct(): MediaStruct
    {
        return $this->getMediaFixture('MediaWithProduct');
    }

    public function getMediaWithManufacturer(): MediaStruct
    {
        $fixture = $this->getMediaFixture('MediaWithManufacturer');

        return $fixture;
    }

    private function getMediaFixture(string $fixtureName): MediaStruct
    {
        return $this->createFixture(
            $fixtureName,
            $this->mediaFixtures,
            EntityFixturesBase::getFixtureRepository('media')
        );
    }
}
