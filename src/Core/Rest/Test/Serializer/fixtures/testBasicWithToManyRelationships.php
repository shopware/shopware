<?php declare(strict_types=1);

$albumId = 'c83a7721-270a-4add-82fd-e60b1dd0c47e';
$thumbnailCollection = new \Shopware\Framework\Struct\StructCollection();

$album = new \Shopware\Api\Media\Struct\MediaAlbumDetailStruct();
$album->setId($albumId);
$album->setName('Manufacturer');
$album->setPosition(12);
$album->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2018-01-15T08:01:16+00:00'));
$album->setCreateThumbnails(true);
$album->setThumbnailSize('200x200');
$album->setThumbnailQuality(90);
$album->setThumbnailHighDpi(true);
$album->setThumbnailHighDpiQuality(60);
$album->setIcon('sprite-blue-folder');

$media = new \Shopware\Api\Media\Struct\MediaBasicStruct();
$media->setId('548faa1f-7846-436c-8594-4f4aea792d96');
$media->setAlbumId($albumId);
$media->setFileName('teaser.jpg');
$media->setMimeType('image/jpg');
$media->setFileSize(93889);
$media->setName('2');
$media->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2012-08-31T00:00:00+00:00'));
$media->setUpdatedAt(date_create_from_format(\DateTime::ATOM, '2017-11-21T11:25:34+00:00'));
$media->setAlbum(clone $album);
$media->addExtension('thumbnails', $thumbnailCollection);

$mediaCollection = new \Shopware\Api\Media\Collection\MediaBasicCollection([$media]);
$album->setMedia($mediaCollection);

return $album;
