<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Struct;

use Shopware\Core\Framework\ORM\Entity;

class TaxAreaRuleTranslationBasicStruct extends Entity
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
}
