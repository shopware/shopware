<?php declare(strict_types=1);

$mediaCollection = new \Shopware\Content\Media\Collection\MediaBasicCollection();
$albumId = 'f343a3c1-19cf-42a7-841a-a0ac5094908c';

$album = new \Shopware\Content\Media\Struct\MediaAlbumBasicStruct();
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

$media1 = new \Shopware\Content\Media\Struct\MediaBasicStruct();
$media1->setId('3e352be2-d858-46dd-9752-9c0f6b544870');
$media1->setAlbumId($albumId);
$media1->setAlbum($album);
$media1->setFileName('Lagerkorn-50klein.jpg');
$media1->setMimeType('image/jpg');
$media1->setFileSize(18921);
$media1->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2012-08-15T00:00:00+00:00'));
$media1->setUpdatedAt(date_create_from_format(\DateTime::ATOM, '2017-11-21T11:25:34+00:00'));
$media1->setName('Lagerkorn-5,0klein');

$media2 = new \Shopware\Content\Media\Struct\MediaBasicStruct();
$media2->setId('f1ad1d0c-0245-4a40-abf2-50f764d16248');
$media2->setAlbumId($albumId);
$media2->setAlbum($album);
$media2->setFileName('Jasmine-Lotus-Cover.jpg');
$media2->setMimeType('image/jpg');
$media2->setFileSize(155633);
$media2->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2012-08-17T00:00:00+00:00'));
$media2->setUpdatedAt(date_create_from_format(\DateTime::ATOM, '2017-11-21T11:25:34+00:00'));
$media2->setName('Jasmine-Lotus-Cover');

$mediaCollection->add($media1);
$mediaCollection->add($media2);

return $mediaCollection;
