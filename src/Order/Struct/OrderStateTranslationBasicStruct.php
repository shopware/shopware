<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\Api\Entity\Entity;

class OrderStateTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $orderStateUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $description;

    public function getOrderStateUuid(): string
    {
        return $this->orderStateUuid;
    }

    public function setOrderStateUuid(string $orderStateUuid): void
    {
        $this->orderStateUuid = $orderStateUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }
}
