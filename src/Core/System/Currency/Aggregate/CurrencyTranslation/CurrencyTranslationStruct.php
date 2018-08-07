<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Currency\CurrencyStruct;
use Shopware\Core\System\Language\LanguageStruct;

class CurrencyTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $shortName;

    /**
     * @var string
     */
    protected $name;
    /**
     * @var CurrencyStruct|null
     */
    protected $currency;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCurrency(): ?CurrencyStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyStruct $currency): void
    {
        $this->currency = $currency;
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
