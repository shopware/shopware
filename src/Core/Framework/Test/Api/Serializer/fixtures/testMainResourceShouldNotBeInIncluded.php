<?php declare(strict_types=1);

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\System\User\UserEntity;

$mediaCollection = new MediaCollection();
$userId = 'f343a3c119cf42a7841aa0ac5094908c';

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
$media->setId('3e352be2d85846dd97529c0f6b544870');
$media->setUser(clone $user);
$media->setUserId($userId);
$media->setMimeType('image/jpg');
$media->setFileExtension('jpg');
$media->setFileSize(18921);
$media->setCreatedAt(new \DateTime('2012-08-15T00:00:00+00:00'));
$media->setUpdatedAt(new \DateTime('2017-11-21T11:25:34+00:00'));
$media->setTitle('Lagerkorn-5,0klein');

$mediaCollection->add($media);
$user->setMedia($mediaCollection);

return $user;
