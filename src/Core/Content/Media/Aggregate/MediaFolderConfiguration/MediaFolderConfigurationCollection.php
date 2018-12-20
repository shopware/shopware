<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaFolderConfigurationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MediaFolderConfigurationEntity::class;
    }
}
