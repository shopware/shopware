<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\CmsBlock;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 *
 * @method void                   add(AppCmsBlockEntity $entity)
 * @method void                   set(string $key, AppCmsBlockEntity $entity)
 * @method \Generator<CmsBlock>   getIterator()
 * @method array<CmsBlock>        getElements()
 * @method AppCmsBlockEntity|null get(string $key)
 * @method AppCmsBlockEntity|null first()
 * @method AppCmsBlockEntity|null last()
 */
class AppCmsBlockCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppCmsBlockEntity::class;
    }
}
