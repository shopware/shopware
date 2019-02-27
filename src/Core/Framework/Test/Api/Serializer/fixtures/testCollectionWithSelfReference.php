<?php declare(strict_types=1);

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;

$parent = new MediaFolderEntity();
$parent->setId('3e352be2d85846dd97529c0f6b544870');
$parent->setChildCount(1);
$parent->setUseParentConfiguration(false);
$parent->setCreatedAt(new DateTime('2012-08-15T00:00:00+00:00'));
$parent->setUpdatedAt(new DateTime('2017-11-21T11:25:34+00:00'));

$child = new MediaFolderEntity();
$child->setId('5846dd97529c0f6b5448713e352be2d8');
$child->setChildCount(1);
$child->setUseParentConfiguration(true);
$child->setParentId('3e352be2d85846dd97529c0f6b544870');
$child->setCreatedAt(new DateTime('2012-08-15T00:00:00+00:00'));
$child->setUpdatedAt(new DateTime('2017-11-21T11:25:34+00:00'));

$parent->setChildren(new MediaFolderCollection([$child]));

return new MediaFolderCollection([$parent]);
