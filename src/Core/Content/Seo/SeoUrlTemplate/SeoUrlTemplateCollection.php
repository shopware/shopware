<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                      add(SeoUrlTemplateEntity $entity)
 * @method void                      set(string $key, SeoUrlTemplateEntity $entity)
 * @method SeoUrlTemplateEntity[]    getIterator()
 * @method SeoUrlTemplateEntity[]    getElements()
 * @method SeoUrlTemplateEntity|null get(string $key)
 * @method SeoUrlTemplateEntity|null first()
 * @method SeoUrlTemplateEntity|null last()
 */
class SeoUrlTemplateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'seo_url_template_collection';
    }

    protected function getExpectedClass(): string
    {
        return SeoUrlTemplateEntity::class;
    }
}
