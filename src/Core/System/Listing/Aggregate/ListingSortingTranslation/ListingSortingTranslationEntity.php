<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\System\Listing\ListingSortingEntity;

class ListingSortingTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $listingSortingId;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var ListingSortingEntity|null
     */
    protected $listingSorting;

    public function getListingSortingId(): string
    {
        return $this->listingSortingId;
    }

    public function setListingSortingId(string $listingSortingId): void
    {
        $this->listingSortingId = $listingSortingId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getListingSorting(): ?ListingSortingEntity
    {
        return $this->listingSorting;
    }

    public function setListingSorting(ListingSortingEntity $listingSorting): void
    {
        $this->listingSorting = $listingSorting;
    }
}
