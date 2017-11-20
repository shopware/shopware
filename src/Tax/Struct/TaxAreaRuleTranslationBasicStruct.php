<?php declare(strict_types=1);

namespace Shopware\Tax\Struct;

use Shopware\Api\Entity\Entity;

class TaxAreaRuleTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $taxAreaRuleUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $name;

    public function getTaxAreaRuleUuid(): string
    {
        return $this->taxAreaRuleUuid;
    }

    public function setTaxAreaRuleUuid(string $taxAreaRuleUuid): void
    {
        $this->taxAreaRuleUuid = $taxAreaRuleUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
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
