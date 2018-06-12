<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation;


use Shopware\Core\Framework\ORM\EntityCollection;

class DiscountSurchargeTranslationCollection extends EntityCollection
{
    /**
     * @var DiscountSurchargeTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? DiscountSurchargeTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): DiscountSurchargeTranslationStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (DiscountSurchargeTranslationStruct $discountSurchargeTranslation) {
            return $discountSurchargeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (DiscountSurchargeTranslationStruct $discountSurchargeTranslation) use ($id) {
            return $discountSurchargeTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return DiscountSurchargeTranslationStruct::class;
    }
}
