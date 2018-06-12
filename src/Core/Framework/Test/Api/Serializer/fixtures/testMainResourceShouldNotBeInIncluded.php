<?php declare(strict_types=1);

$albumId = 'f343a3c1-19cf-42a7-841a-a0ac5094908c';
$mediaCollection = new \Shopware\Core\Content\Media\MediaBasicCollection();

$album = new \Shopware\Core\Content\Media\Aggregate\MediaAlbum\Struct\MediaAlbumDetailStruct();
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

$media = new \Shopware\Core\Content\Media\MediaBasicStruct();
$media->setId('3e352be2-d858-46dd-9752-9c0f6b544870');
$media->setAlbumId($albumId);
$media->setAlbum(clone $album);
$media->setFileName('Lagerkorn-50klein.jpg');
$media->setMimeType('image/jpg');
$media->setFileSize(18921);
$media->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2012-08-15T00:00:00+00:00'));
$media->setUpdatedAt(date_create_from_format(\DateTime::ATOM, '2017-11-21T11:25:34+00:00'));
$media->setName('Lagerkorn-5,0klein');

$mediaCollection->add($media);
$album->setMedia($mediaCollection);

return $album;
