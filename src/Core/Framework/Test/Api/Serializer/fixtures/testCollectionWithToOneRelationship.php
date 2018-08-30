<?php declare(strict_types=1);

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\System\User\UserStruct;

$mediaCollection = new MediaCollection();
$userId = '6f51622e-b381-4c75-ae02-63cece27ce72';

$user = new UserStruct();
$user->setId($userId);
$user->setName('Manufacturer');
$user->setPassword('password');
$user->setUsername('user1');
$user->setActive(true);
$user->setEmail('user1@shop.de');
$user->setFailedLogins(0);
$user->setLastLogin(date_create_from_format(\DateTime::ATOM, '2018-01-15T08:01:16+00:00'));
$user->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2018-01-15T08:01:16+00:00'));

$media1 = new MediaStruct();
$media1->setId('3e352be2-d858-46dd-9752-9c0f6b544870');
$media1->setUser($user);
$media1->setUserId($userId);
$media1->setMimeType('image/jpg');
$media1->setFileExtension('jpg');
$media1->setFileSize(18921);
$media1->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2012-08-15T00:00:00+00:00'));
$media1->setUpdatedAt(date_create_from_format(\DateTime::ATOM, '2017-11-21T11:25:34+00:00'));
$media1->setName('Lagerkorn-5,0klein');

$media2 = new MediaStruct();
$media2->setId('f1ad1d0c-0245-4a40-abf2-50f764d16248');
$media2->setUser($user);
$media2->setUserId($userId);
$media2->setMimeType('image/jpg');
$media2->setFileExtension('jpg');
$media2->setFileSize(155633);
$media2->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2012-08-17T00:00:00+00:00'));
$media2->setUpdatedAt(date_create_from_format(\DateTime::ATOM, '2017-11-21T11:25:34+00:00'));
$media2->setName('Jasmine-Lotus-Cover');

$mediaCollection->add($media1);
$mediaCollection->add($media2);

return $mediaCollection;
