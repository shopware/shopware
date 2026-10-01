<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Extension;

use Shopware\Core\Framework\Feature;
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
    protected array $addedCodes = [];

    /**
     * @var array<string>
     *
     * @deprecated tag:v6.8.0 - Will be removed without replacement. Automatic promotions can no longer be removed.
     */
    protected array $blockedPromotionIds = [];

    public function addCode(string $code): void
    {
        if ($code === '') {
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
        if ($code === '') {
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

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement. Automatic promotions can no longer be removed.
     */
    public function blockPromotion(string $id): void
    {
        Feature::triggerDeprecationOrThrow('PERMANENT_AUTOMATIC_PROMOTIONS', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        if (Feature::isActive('PERMANENT_AUTOMATIC_PROMOTIONS')) {
            return;
        }

        if ($id === '') {
            return;
        }

        if (!\in_array($id, $this->blockedPromotionIds, true)) {
            $this->blockedPromotionIds[] = $id;
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement. Automatic promotions can no longer be removed.
     */
    public function isPromotionBlocked(string $id): bool
    {
        Feature::triggerDeprecationOrThrow('PERMANENT_AUTOMATIC_PROMOTIONS', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        if (Feature::isActive('PERMANENT_AUTOMATIC_PROMOTIONS')) {
            return false;
        }

        return \in_array($id, $this->blockedPromotionIds, true);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement. Automatic promotions can no longer be removed.
     *
     * @return array<string>
     */
    public function getBlockedPromotions(): array
    {
        Feature::triggerDeprecationOrThrow('PERMANENT_AUTOMATIC_PROMOTIONS', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        if (Feature::isActive('PERMANENT_AUTOMATIC_PROMOTIONS')) {
            return [];
        }

        return $this->blockedPromotionIds;
    }

    public function merge(self $extension): static
    {
        $new = clone $this;

        foreach ($extension->getCodes() as $code) {
            $new->addCode($code);
        }

        Feature::callSilentIfInactive('PERMANENT_AUTOMATIC_PROMOTIONS', static function () use ($extension, $new): void {
            foreach ($extension->getBlockedPromotions() as $id) {
                $new->blockPromotion($id);
            }
        });

        return $new;
    }
}
