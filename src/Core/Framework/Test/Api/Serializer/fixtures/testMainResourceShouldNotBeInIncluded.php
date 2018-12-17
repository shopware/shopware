<?php declare(strict_types=1);

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\System\User\UserEntity;

$mediaCollection = new MediaCollection();
$userId = 'f343a3c1-19cf-42a7-841a-a0ac5094908c';

$user = new UserEntity();
$user->setId($userId);
$user->setName('Manufacturer');
$user->setPassword('password');
$user->setUsername('user1');
$user->setActive(true);
$user->setEmail('user1@shop.de');
$user->setFailedLogins(0);
$user->setLastLogin(date_create_from_format(\DateTime::ATOM, '2018-01-15T08:01:16+00:00'));
$user->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2018-01-15T08:01:16+00:00'));

$media = new MediaEntity();
$media->setId('3e352be2-d858-46dd-9752-9c0f6b544870');
$media->setUser(clone $user);
$media->setUserId($userId);
$media->setMimeType('image/jpg');
$media->setFileExtension('jpg');
$media->setFileSize(18921);
$media->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2012-08-15T00:00:00+00:00'));
$media->setUpdatedAt(date_create_from_format(\DateTime::ATOM, '2017-11-21T11:25:34+00:00'));
$media->setTitle('Lagerkorn-5,0klein');

$mediaCollection->add($media);
$user->setMedia($mediaCollection);

return $user;
