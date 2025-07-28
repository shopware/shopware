<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Framework\Context;

abstract class AbstractMediaFolderConfigurationLoader
{
    abstract public function load(string $mediaFolderId, ?Context $context = null): ?MediaFolderConfigurationEntity;
}
