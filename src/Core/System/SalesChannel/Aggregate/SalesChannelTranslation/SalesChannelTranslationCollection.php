<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package sales-channel
 *
 * @extends EntityCollection<SalesChannelTranslationEntity>
 */
class SalesChannelTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
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

    /**
     * @return list<string>
     */
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
