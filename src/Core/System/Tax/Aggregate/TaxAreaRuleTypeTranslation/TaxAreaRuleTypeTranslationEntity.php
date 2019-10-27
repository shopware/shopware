<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleType\TaxAreaRuleTypeEntity;

class TaxAreaRuleTypeTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $taxAreaRuleTypeId;

    /**
     * @var string|null
     */
    protected $typeName;

    /**
     * @var TaxAreaRuleTypeEntity|null
     */
    protected $taxAreaRuleType;

    public function getTaxAreaRuleTypeId(): string
    {
        return $this->taxAreaRuleTypeId;
    }

    public function setTaxAreaRuleTypeId(string $taxAreaRuleTypeId): void
    {
        $this->taxAreaRuleTypeId = $taxAreaRuleTypeId;
    }

    public function getTypeName(): ?string
    {
        return $this->typeName;
    }

    public function setTypeName(?string $typeName): void
    {
        $this->typeName = $typeName;
    }

    public function getTaxAreaRuleType(): ?TaxAreaRuleTypeEntity
    {
        return $this->taxAreaRuleType;
    }

    public function setTaxAreaRuleType(?TaxAreaRuleTypeEntity $taxAreaRuleType): void
    {
        $this->taxAreaRuleType = $taxAreaRuleType;
    }
}
