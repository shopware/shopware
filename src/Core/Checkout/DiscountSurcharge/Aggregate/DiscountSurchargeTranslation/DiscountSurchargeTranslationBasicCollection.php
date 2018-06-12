<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation;

use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class DiscountSurchargeTranslationBasicCollection extends EntityCollection
{
    /**
     * @var DiscountSurchargeTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? DiscountSurchargeTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): DiscountSurchargeTranslationBasicStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (DiscountSurchargeTranslationBasicStruct $discountSurchargeTranslation) {
            return $discountSurchargeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (DiscountSurchargeTranslationBasicStruct $discountSurchargeTranslation) use ($id) {
            return $discountSurchargeTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return DiscountSurchargeTranslationBasicStruct::class;
    }
}
