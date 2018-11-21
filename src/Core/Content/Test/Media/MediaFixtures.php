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
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
            ],
            'NamedMimePngEtxPngCatalog' => [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'catalogId' => $this->catalogId,
            ],
            'NamedMimeTxtEtxTxt' => [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test file',
                'mimeType' => 'plain/txt',
                'fileExtension' => 'txt',
            ],
            'NamedMimeJpgEtxJpgCatalog' => [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'catalogId' => $this->catalogId,
            ],
            'NamedMimePdfEtxPdfCatalog' => [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'mimeType' => 'application/pdf',
                'fileExtension' => 'pdf',
                'catalogId' => $this->catalogId,
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
