<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Page;

use Shopware\Storefront\Framework\Page\PageletStruct;

class CartInfoPageletStruct extends PageletStruct
{
    /**
     * @var int
     */
    protected $cartQuantity;

    /**
     * @var float
     */
    protected $cartAmount;

    /**
     * @var int
     */
    protected $notesQuantity;

    /**
     * @var bool
     */
    protected $userLoggedIn;

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
    public function isUserLoggedIn(): bool
    {
        return $this->userLoggedIn;
    }

    /**
     * @param bool $userLoggedIn
     */
    public function setUserLoggedIn(bool $userLoggedIn): void
    {
        $this->userLoggedIn = $userLoggedIn;
    }
}
