<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PromotionIndividualCodeEntity>
 */
class PromotionIndividualCodeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_individual_code_collection';
    }

    /**
     * @returns array<string>
     */
    public function getCodeArray(): array
    {
        $codes = [];
        foreach ($this->getIterator() as $codeEntity) {
            $codes[] = $codeEntity->getCode();
        }

        return $codes;
    }

    protected function getExpectedClass(): string
    {
        return PromotionIndividualCodeEntity::class;
    }
}
