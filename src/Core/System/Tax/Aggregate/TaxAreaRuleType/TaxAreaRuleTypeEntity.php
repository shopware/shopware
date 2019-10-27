<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRuleType;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleCollection;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTypeTranslation\TaxAreaRuleTypeTranslationCollection;

class TaxAreaRuleTypeEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $typeName;

    /**
     * @var string
     */
    protected $technicalName;

    /**
     * @var TaxAreaRuleCollection|null
     */
    protected $taxAreaRules;

    /**
     * @var TaxAreaRuleTypeTranslationCollection|null
     */
    protected $translations;

    public function getTypeName(): string
    {
        return $this->typeName;
    }

    public function setTypeName(string $typeName): void
    {
        $this->typeName = $typeName;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getTaxAreaRules(): ?TaxAreaRuleCollection
    {
        return $this->taxAreaRules;
    }

    public function setTaxAreaRules(?TaxAreaRuleCollection $taxAreaRules): void
    {
        $this->taxAreaRules = $taxAreaRules;
    }

    public function getTranslations(): ?TaxAreaRuleTypeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(?TaxAreaRuleTypeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
