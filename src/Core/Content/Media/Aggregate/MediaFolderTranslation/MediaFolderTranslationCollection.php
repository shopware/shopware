<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaFolderTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MediaFolderTranslationEntity::class;
    }
}
