<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;

class SalesChannelTranslationCollection extends EntityCollection
{
    /**
     * @var SalesChannelTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? SalesChannelTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): SalesChannelTranslationStruct
    {
        return parent::current();
    }

    public function getSalesChannelIds(): array
    {
        return $this->fmap(function (SalesChannelTranslationStruct $salesChannelTranslation) {
            return $salesChannelTranslation->getSalesChannelId();
        });
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(function (SalesChannelTranslationStruct $salesChannelTranslation) use ($id) {
            return $salesChannelTranslation->getSalesChannelId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (SalesChannelTranslationStruct $salesChannelTranslation) {
            return $salesChannelTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (SalesChannelTranslationStruct $salesChannelTranslation) use ($id) {
            return $salesChannelTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelTranslationStruct::class;
    }
}
