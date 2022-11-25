<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\CmsBlock;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package content
 *
 * @internal
 *
 * @extends EntityCollection<AppCmsBlockEntity>
 */
class AppCmsBlockCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppCmsBlockEntity::class;
    }
}
