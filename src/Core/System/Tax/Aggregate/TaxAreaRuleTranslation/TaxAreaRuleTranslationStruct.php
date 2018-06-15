<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Language\LanguageStruct;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleStruct;

class TaxAreaRuleTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $taxAreaRuleId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var TaxAreaRuleStruct|null
     */
    protected $taxAreaRule;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    public function getTaxAreaRuleId(): string
    {
        return $this->taxAreaRuleId;
    }

    public function setTaxAreaRuleId(string $taxAreaRuleId): void
    {
        $this->taxAreaRuleId = $taxAreaRuleId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTaxAreaRule(): ?TaxAreaRuleStruct
    {
        return $this->taxAreaRule;
    }

    public function setTaxAreaRule(TaxAreaRuleStruct $taxAreaRule): void
    {
        $this->taxAreaRule = $taxAreaRule;
    }

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }
}
