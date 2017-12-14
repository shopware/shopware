<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Struct;

use Shopware\Api\Entity\Entity;

class ListingSortingTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $listingSortingUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $label;

    public function getListingSortingUuid(): string
    {
        return $this->listingSortingUuid;
    }

    public function setListingSortingUuid(string $listingSortingUuid): void
    {
        $this->listingSortingUuid = $listingSortingUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
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
