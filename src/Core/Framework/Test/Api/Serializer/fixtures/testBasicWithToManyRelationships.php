<?php declare(strict_types=1);

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\User\UserEntity;

$mediaCollection = new StructCollection();

$userId = '6f51622eb3814c75ae0263cece27ce72';

$user = new UserEntity();
$user->setId($userId);
$user->setFirstName('Manufacturer');
$user->setLastName('');
$user->setPassword('password');
$user->setUsername('user1');
$user->setActive(true);
$user->setEmail('user1@shop.de');
$user->setCreatedAt(new \DateTime('2018-01-15T08:01:16+00:00'));

$media = new MediaEntity();
$media->setId('548faa1f7846436c85944f4aea792d96');
$media->setUserId($userId);
$media->setMimeType('image/jpg');
$media->setFileExtension('jpg');
$media->setFileSize(93889);
$media->setTitle('2');
$media->setCreatedAt(new \DateTime('2012-08-31T00:00:00+00:00'));
$media->setUpdatedAt(new \DateTime('2017-11-21T11:25:34+00:00'));
$media->setUser(clone $user);

$mediaCollection = new MediaCollection([$media]);
$user->setMedia($mediaCollection);

return $user;
