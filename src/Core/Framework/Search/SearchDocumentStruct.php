<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class SearchDocumentStruct extends Entity
{
    /**
     * @var string
     */
    protected $keyword;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $entity;

    /**
     * @var string
     */
    protected $entityId;

    /**
     * @var float
     */
    protected $ranking;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): void
    {
        $this->keyword = $keyword;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): void
    {
        $this->entityId = $entityId;
    }

    public function getRanking(): float
    {
        return $this->ranking;
    }

    public function setRanking(float $ranking): void
    {
        $this->ranking = $ranking;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }
}
