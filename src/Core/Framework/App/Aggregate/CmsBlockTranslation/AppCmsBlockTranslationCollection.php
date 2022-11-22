<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package content
 *
 * @internal
 *
 * @extends EntityCollection<AppCmsBlockTranslationEntity>
 */
class AppCmsBlockTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppCmsBlockTranslationEntity::class;
    }
}
