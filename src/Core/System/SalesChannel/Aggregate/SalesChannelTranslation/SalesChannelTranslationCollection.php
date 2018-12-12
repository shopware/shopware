<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SalesChannelTranslationCollection extends EntityCollection
{
    /**
     * @var SalesChannelTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? SalesChannelTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): SalesChannelTranslationEntity
    {
        return parent::current();
    }

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

    protected function getExpectedClass(): string
    {
        return SalesChannelTranslationEntity::class;
    }
}
