<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package sales-channel
 *
 * @extends EntityCollection<SalesChannelTypeTranslationEntity>
 */
class SalesChannelTypeTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getSalesChannelTypeIds(): array
    {
        return $this->fmap(function (SalesChannelTypeTranslationEntity $salesChannelTypeTranslation) {
            return $salesChannelTypeTranslation->getSalesChannelTypeId();
        });
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(function (SalesChannelTypeTranslationEntity $salesChannelTypeTranslation) use ($id) {
            return $salesChannelTypeTranslation->getSalesChannelTypeId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(function (SalesChannelTypeTranslationEntity $salesChannelTranslation) {
            return $salesChannelTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (SalesChannelTypeTranslationEntity $salesChannelTranslation) use ($id) {
            return $salesChannelTranslation->getLanguageId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'sales_channel_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelTypeTranslationEntity::class;
    }
}
