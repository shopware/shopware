<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Test\EntityFixturesBase;

trait MediaFixtures
{
    use EntityFixturesBase;

    /**
     * @var array
     */
    public static $mediaFixtures;

    /**
     * @var EntityRepository
     */
    public static $mediaFixtureRepository;

    /**
     * @beforeClass
     */
    public static function initializeTaxFixtures(): void
    {
        $catalogId = Uuid::uuid4()->getHex();

        MediaFixtures::$mediaFixtures = [
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
                'catalogId' => $catalogId,
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
                'catalogId' => $catalogId,
            ],
            'NamedMimePdfEtxPdfCatalog' => [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test_media',
                'mimeType' => 'application/pdf',
                'fileExtension' => 'pdf',
                'catalogId' => $catalogId,
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
            'Catalog' => [
                'id' => $catalogId,
                'name' => 'test catalog',
            ],
        ];

        MediaFixtures::$mediaFixtureRepository = EntityFixturesBase::getFixtureRepository('media');

        MediaFixtures::setupAuxFixtures();
    }

    public static function setupAuxFixtures(): void
    {
        EntityFixturesBase::getFixtureRepository('catalog')->upsert(
            [
                MediaFixtures::$mediaFixtures['Catalog'],
            ],
            Context::createDefaultContext(Defaults::TENANT_ID));
    }

    /**
     * @afterClass
     */
    public static function tearDownAuxFixtures(): void
    {
        EntityFixturesBase::getFixtureRepository('catalog')->delete([
            ['id' => MediaFixtures::$mediaFixtures['Catalog']['id']], ],
            Context::createDefaultContext(Defaults::TENANT_ID));
    }

    public function getContextWithCatalogAndWriteAccess(): Context
    {
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $context = $context->createWithCatalogIds([MediaFixtures::$mediaFixtures['Catalog']['id']]);
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

    public function getPngInCatalog(): MediaStruct
    {
        return $this->getMediaFixture('NamedMimePngEtxPngCatalog');
    }

    public function getJpgInCatalog(): MediaStruct
    {
        return $this->getMediaFixture('NamedMimeJpgEtxJpgCatalog');
    }

    public function getPdfInCatalog(): MediaStruct
    {
        return $this->getMediaFixture('NamedMimePdfEtxPdfCatalog');
    }

    public function getMediaWithThumbnail(): MediaStruct
    {
        return $this->getMediaFixture('NamedWithThumbnail');
    }

    private function getMediaFixture(string $fixtureName): MediaStruct
    {
        return $this->createFixture($fixtureName, MediaFixtures::$mediaFixtures, MediaFixtures::$mediaFixtureRepository);
    }
}
