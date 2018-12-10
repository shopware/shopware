<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Language\LanguageStruct;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetStruct;

class SnippetStruct extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $setId;

    /**
     * @var string
     */
    protected $translationKey;

    /**
     * @var string
     */
    protected $value;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var LanguageStruct
     */
    protected $language;

    /**
     * @var SnippetSetStruct
     */
    protected $set;

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    /**
     * @return string
     */
    public function getSetId(): string
    {
        return $this->setId;
    }

    /**
     * @param string $setId
     */
    public function setSetId(string $setId): void
    {
        $this->setId = $setId;
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function setTranslationKey(string $translationKey): void
    {
        $this->translationKey = $translationKey;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getCreatedAt(): ?\DateTime
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

    public function getLanguage(): LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }

    /**
     * @return SnippetSetStruct
     */
    public function getSet(): SnippetSetStruct
    {
        return $this->set;
    }

    /**
     * @param SnippetSetStruct $set
     */
    public function setSet(SnippetSetStruct $set): void
    {
        $this->set = $set;
    }
}
