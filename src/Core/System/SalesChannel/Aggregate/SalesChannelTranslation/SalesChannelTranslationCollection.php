<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(SalesChannelTranslationEntity $entity)
 * @method void                               set(string $key, SalesChannelTranslationEntity $entity)
 * @method SalesChannelTranslationEntity[]    getIterator()
 * @method SalesChannelTranslationEntity[]    getElements()
 * @method SalesChannelTranslationEntity|null get(string $key)
 * @method SalesChannelTranslationEntity|null first()
 * @method SalesChannelTranslationEntity|null last()
 */
class SalesChannelTranslationCollection extends EntityCollection
{
    public function getSalesChannelIds(): array
    {
        return $this->fmap(function (SalesChannelTranslationEntity $salesChannelTranslation) {
            return $salesChannelTranslation->getSalesChannelId();
        });
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(function (SalesChannelTranslationEntity $salesChannelTranslation) use ($id) {
            return $salesChannelTranslation->getSalesChannelId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (SalesChannelTranslationEntity $salesChannelTranslation) {
            return $salesChannelTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (SalesChannelTranslationEntity $salesChannelTranslation) use ($id) {
            return $salesChannelTranslation->getLanguageId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'sales_channel_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelTranslationEntity::class;
    }
}
