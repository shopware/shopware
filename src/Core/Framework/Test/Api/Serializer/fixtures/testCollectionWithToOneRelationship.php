<?php declare(strict_types=1);

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\System\User\UserEntity;

$mediaCollection = new MediaCollection();
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

$media1 = new MediaEntity();
$media1->setId('3e352be2d85846dd97529c0f6b544870');
$media1->setUser($user);
$media1->setUserId($userId);
$media1->setMimeType('image/jpg');
$media1->setFileExtension('jpg');
$media1->setFileSize(18921);
$media1->setCreatedAt(new \DateTime('2012-08-15T00:00:00+00:00'));
$media1->setUpdatedAt(new \DateTime('2017-11-21T11:25:34+00:00'));
$media1->setTitle('Lagerkorn-5,0klein');

$media2 = new MediaEntity();
$media2->setId('f1ad1d0c02454a40abf250f764d16248');
$media2->setUser($user);
$media2->setUserId($userId);
$media2->setMimeType('image/jpg');
$media2->setFileExtension('jpg');
$media2->setFileSize(155633);
$media2->setCreatedAt(new \DateTime('2012-08-17T00:00:00+00:00'));
$media2->setUpdatedAt(new \DateTime('2017-11-21T11:25:34+00:00'));
$media2->setTitle('Jasmine-Lotus-Cover');

$mediaCollection->add($media1);
$mediaCollection->add($media2);

return $mediaCollection;
