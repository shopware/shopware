<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class DiscountSurchargeTranslationCollection extends EntityCollection
{
    /**
     * @var DiscountSurchargeTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? DiscountSurchargeTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): DiscountSurchargeTranslationEntity
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (DiscountSurchargeTranslationEntity $discountSurchargeTranslation) {
            return $discountSurchargeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (DiscountSurchargeTranslationEntity $discountSurchargeTranslation) use ($id) {
            return $discountSurchargeTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return DiscountSurchargeTranslationEntity::class;
    }
}
