<?php declare(strict_types=1);

namespace Shopware\System\Listing\Aggregate\ListingSortingTranslation\Struct;

use Shopware\Framework\ORM\Entity;

class ListingSortingTranslationBasicStruct extends Entity
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
}
