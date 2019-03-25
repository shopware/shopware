<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlTemplate;

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
    public function filterBySalesChannelId(string $salesChannelId): SeoUrlTemplateCollection
    {
        return $this->filterByProperty('salesChannelId', $salesChannelId);
    }

    public function getByRouteName(string $salesChannelId, string $routeName): ?SeoUrlTemplateEntity
    {
        return $this->filterBySalesChannelId($salesChannelId)
            ->filterByProperty('routeName', $routeName)
            ->first();
    }

    protected function getExpectedClass(): string
    {
        return SeoUrlTemplateEntity::class;
    }
}
