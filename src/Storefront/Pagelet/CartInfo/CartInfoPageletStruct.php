<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CartInfo;

use Shopware\Storefront\Framework\Page\PageletStruct;

class CartInfoPageletStruct extends PageletStruct
{
    /**
     * @var int
     */
    protected $cartQuantity = 0;

    /**
     * @var float
     */
    protected $cartAmount = 0.00;

    /**
     * @var int
     */
    protected $notesQuantity = 0;

    /**
     * @var bool
     */
    protected $customerLoggedIn = false;

    /**
     * @return int
     */
    public function getCartQuantity(): int
    {
        return $this->cartQuantity;
    }

    /**
     * @param int $cartQuantity
     */
    public function setCartQuantity(int $cartQuantity): void
    {
        $this->cartQuantity = $cartQuantity;
    }

    /**
     * @return float
     */
    public function getCartAmount(): float
    {
        return $this->cartAmount;
    }

    /**
     * @param float $cartAmount
     */
    public function setCartAmount(float $cartAmount): void
    {
        $this->cartAmount = $cartAmount;
    }

    /**
     * @return int
     */
    public function getNotesQuantity(): int
    {
        return $this->notesQuantity;
    }

    /**
     * @param int $notesQuantity
     */
    public function setNotesQuantity(int $notesQuantity): void
    {
        $this->notesQuantity = $notesQuantity;
    }

    /**
     * @return bool
     */
    public function isCustomerLoggedIn(): bool
    {
        return $this->customerLoggedIn;
    }

    /**
     * @param bool $customerLoggedIn
     */
    public function setCustomerLoggedIn(bool $customerLoggedIn): void
    {
        $this->customerLoggedIn = $customerLoggedIn;
    }
}
