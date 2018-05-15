<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Struct;

use Shopware\Framework\ORM\Entity;

class OrderStateTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $orderStateId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $description;

    public function getOrderStateId(): string
    {
        return $this->orderStateId;
    }

    public function setOrderStateId(string $orderStateId): void
    {
        $this->orderStateId = $orderStateId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
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
