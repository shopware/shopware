<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Extension;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class CartExtension extends Struct
{
    /**
     * this is the key that should be
     * used for the cart extension
     */
    final public const KEY = 'cart-promotions';

    /**
     * @var array<string>
     */
    protected $addedCodes = [];

    /**
     * @var array<string>
     */
    protected $blockedPromotionIds = [];

    public function addCode(string $code): void
    {
        if (empty($code)) {
            return;
        }

        if (!\in_array($code, $this->addedCodes, true)) {
            $this->addedCodes[] = $code;
        }
    }

    public function hasCode(string $code): bool
    {
        return \in_array($code, $this->addedCodes, true);
    }

    public function removeCode(string $code): void
    {
        if (empty($code)) {
            return;
        }

        if (\in_array($code, $this->addedCodes, true)) {
            $newList = [];
            foreach ($this->addedCodes as $existingCode) {
                if ($existingCode !== $code) {
                    $newList[] = $existingCode;
                }
            }
            $this->addedCodes = $newList;
        }
    }

    /**
     * @return array<string>
     */
    public function getCodes(): array
    {
        return $this->addedCodes;
    }

    public function blockPromotion(string $id): void
    {
        if (empty($id)) {
            return;
        }

        if (!\in_array($id, $this->blockedPromotionIds, true)) {
            $this->blockedPromotionIds[] = $id;
        }
    }

    public function isPromotionBlocked(string $id): bool
    {
        return \in_array($id, $this->blockedPromotionIds, true);
    }
}
