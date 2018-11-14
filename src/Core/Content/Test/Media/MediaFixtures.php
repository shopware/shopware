<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaStruct;
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
    public function initializeMediaFixtures(): void
    {
        $this->catalogId = Uuid::uuid4()->getHex();

        $this->mediaFixtures = [
            'NamedEmpty' => [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test_media',
            ],
            'NamedMimePng' => [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'mimeType' => 'image/png',
            ],
            'NamedMimePngEtxPng' => [
                'id' => $namedMimePngEtxPngUuid = Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => $namedMimePngEtxPngUuid . '-1314343'
            ],
            'NamedMimePngEtxPngCatalog' => [
                'id' => $namedMimePngEtxPngCatalogUuid = Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => $namedMimePngEtxPngCatalogUuid . '-12312351',
            ],
            'NamedMimeTxtEtxTxt' => [
                'id' => $namedMimeTxtEtxTxtUuid = Uuid::uuid4()->getHex(),
                'name' => 'test file',
                'mimeType' => 'plain/txt',
                'fileExtension' => 'txt',
                'fileName' => $namedMimeTxtEtxTxtUuid . '-131513235',
            ],
            'NamedMimeJpgEtxJpgCatalog' => [
                'id' => $NamedMimeJpgEtxJpgCatalogUuid = Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileName' => $NamedMimeJpgEtxJpgCatalogUuid . '-5434541313',
            ],
            'NamedMimePdfEtxPdfCatalog' => [
                'id' => $namedMimePdfEtxPdfCatalogUuid =  Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'mimeType' => 'application/pdf',
                'fileExtension' => 'pdf',
                'fileName' => $namedMimePdfEtxPdfCatalogUuid . '1323213213'
            ],
            'NamedWithThumbnail' => [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'thumbnails' => [
                    [
                        'width' => 200,
                        'height' => 200,
                        'highDpi' => false,
                    ],
                ],
            ],
            '_Catalog' => [
                'id' => $this->catalogId,
                'name' => 'test catalog',
            ],
        ];

        MediaFixtures::$mediaFixtureRepository = EntityFixturesBase::getFixtureRepository('media');
    }

    public function getContextWithCatalogAndWriteAccess(): Context
    {
        $context = Context::createDefaultContext();

        $context = $context
            ->createWithCatalogIds([$this->catalogId]);

        $context
            ->getWriteProtection()
            ->allow(MediaProtectionFlags::WRITE_META_INFO);

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

    public function getPngInCatalog(): MediaStruct
    {
        EntityFixturesBase::getFixtureRepository('catalog')
            ->upsert([$this->mediaFixtures['_Catalog']], Context::createDefaultContext());

        return $this->getMediaFixture('NamedMimePngEtxPngCatalog');
    }

    public function getJpgInCatalog(): MediaStruct
    {
        EntityFixturesBase::getFixtureRepository('catalog')
            ->upsert([$this->mediaFixtures['_Catalog']], Context::createDefaultContext());

        return $this->getMediaFixture('NamedMimeJpgEtxJpgCatalog');
    }

    public function getPdfInCatalog(): MediaStruct
    {
        EntityFixturesBase::getFixtureRepository('catalog')
            ->upsert([$this->mediaFixtures['_Catalog']], Context::createDefaultContext());

        return $this->getMediaFixture('NamedMimePdfEtxPdfCatalog');
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
