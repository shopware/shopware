<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Language\LanguageStruct;
use Shopware\Core\System\Listing\ListingSortingStruct;

class ListingSortingTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $listingSortingId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var ListingSortingStruct|null
     */
    protected $listingSorting;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    public function getListingSortingId(): string
    {
        return $this->listingSortingId;
    }

    public function setListingSortingId(string $listingSortingId): void
    {
        $this->listingSortingId = $listingSortingId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getListingSorting(): ?ListingSortingStruct
    {
        return $this->listingSorting;
    }

    public function setListingSorting(ListingSortingStruct $listingSorting): void
    {
        $this->listingSorting = $listingSorting;
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
