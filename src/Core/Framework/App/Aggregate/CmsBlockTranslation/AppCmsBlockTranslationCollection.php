<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 *
 * @method void                                  add(AppCmsBlockTranslationEntity $entity)
 * @method void                                  set(string $key, AppCmsBlockTranslationEntity $entity)
 * @method \Generator<CmsBlockTranslationEntity> getIterator()
 * @method array<CmsBlockTranslationEntity>      getElements()
 * @method AppCmsBlockTranslationEntity|null     get(string $key)
 * @method AppCmsBlockTranslationEntity|null     first()
 * @method AppCmsBlockTranslationEntity|null     last()
 */
class AppCmsBlockTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppCmsBlockTranslationEntity::class;
    }
}
