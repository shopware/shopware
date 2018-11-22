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
     * @var string
     */
    private $catalogId;

    /**
     * @before
     */
    public static function initializeMediaFixtures(): void
    {
        MediaFixtures::$mediaFixtures = [
            'NamedEmpty' => [
                'id' => Uuid::uuid4()->getHex(),
            ],
            'NamedMimePng' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'image/png',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
            ],
            'NamedMimePngEtxPng' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithExtension',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
            ],

            'NamedMimeTxtEtxTxt' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'plain/txt',
                'fileExtension' => 'txt',
                'fileName' => 'textFileWithExtension',
                'fileSize' => 1024,
                'mediaType' => new BinaryType(),
            ],
            'NamedMimeJpgEtxJpg' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileName' => 'jpgFileWithExtensionAndCatalog',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
            ],
            'NamedMimePdfEtxPdf' => [
                'id' => Uuid::uuid4()->getHex(),
                'mimeType' => 'application/pdf',
                'fileExtension' => 'pdf',
                'fileName' => 'pdfFileWithExtensionAndCatalog',
                'fileSize' => 1024,
                'mediaType' => new DocumentType(),
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
        ];

        MediaFixtures::$mediaFixtureRepository = EntityFixturesBase::getFixtureRepository('media');
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

    private function getMediaFixture(string $fixtureName): MediaStruct
    {
        return $this->createFixture(
            $fixtureName,
            $this->mediaFixtures,
            EntityFixturesBase::getFixtureRepository('media')
        );
    }
}
