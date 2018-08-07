<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;

class SalesChannelTypeTranslationCollection extends EntityCollection
{
    /**
     * @var SalesChannelTypeTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? SalesChannelTypeTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): SalesChannelTypeTranslationStruct
    {
        return parent::current();
    }

    public function getSalesChannelTypeIds(): array
    {
        return $this->fmap(function (SalesChannelTypeTranslationStruct $salesChannelTypeTranslation) {
            return $salesChannelTypeTranslation->getSalesChannelTypeId();
        });
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(function (SalesChannelTypeTranslationStruct $salesChannelTypeTranslation) use ($id) {
            return $salesChannelTypeTranslation->getSalesChannelTypeId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (SalesChannelTypeTranslationStruct $salesChannelTranslation) {
            return $salesChannelTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (SalesChannelTypeTranslationStruct $salesChannelTranslation) use ($id) {
            return $salesChannelTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelTypeTranslationStruct::class;
    }
}
