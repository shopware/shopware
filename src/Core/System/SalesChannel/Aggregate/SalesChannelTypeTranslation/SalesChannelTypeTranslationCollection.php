<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SalesChannelTypeTranslationCollection extends EntityCollection
{
    /**
     * @var SalesChannelTypeTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? SalesChannelTypeTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): SalesChannelTypeTranslationEntity
    {
        return parent::current();
    }

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

    protected function getExpectedClass(): string
    {
        return SalesChannelTypeTranslationEntity::class;
    }
}
