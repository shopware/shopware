<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CmsPageCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CmsPageEntity::class;
    }
}
