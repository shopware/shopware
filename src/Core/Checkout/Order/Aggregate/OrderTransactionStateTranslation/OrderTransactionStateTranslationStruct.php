<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class OrderTransactionStateTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $orderTransactionStateId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var LanguageStruct
     */
    protected $language;

    /**
     * @var OrderTransactionStateStruct
     */
    protected $orderTransactionState;

    public function getOrderTransactionStateId(): string
    {
        return $this->orderTransactionStateId;
    }

    public function setOrderTransactionStateId(string $orderTransactionStateId): void
    {
        $this->orderTransactionStateId = $orderTransactionStateId;
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

    public function getLanguage(): LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }

    public function getOrderTransactionState(): OrderTransactionStateStruct
    {
        return $this->orderTransactionState;
    }

    public function setOrderTransactionState(OrderTransactionStateStruct $orderTransactionState): void
    {
        $this->orderTransactionState = $orderTransactionState;
    }
}
