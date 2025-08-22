<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Extension\CheckoutPlaceOrderExtension;
use Shopware\Core\Checkout\Promotion\Cart\Extension\LockExtension;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionRedemptionLocker implements EventSubscriberInterface
{
    private const LOCK_TTL = 5;

    public function __construct(private readonly LockFactory $lockFactory)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ExtensionDispatcher::pre(CheckoutPlaceOrderExtension::NAME) => 'acquireLocks',
            ExtensionDispatcher::error(CheckoutPlaceOrderExtension::NAME) => 'releaseLocks',
            ExtensionDispatcher::post(CheckoutPlaceOrderExtension::NAME) => 'releaseLocks',
        ];
    }

    public function acquireLocks(CheckoutPlaceOrderExtension $extension): void
    {
        $locks = [];
        foreach ($extension->getCart()->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== PromotionProcessor::LINE_ITEM_TYPE) {
                continue;
            }

            if (!$lineItem->getPayloadValue('limitedRedemptions')) {
                // no limited redemptions, no lock needed
                continue;
            }

            // use code for individual or global codes (reduces conflicts) and promotionId for automatic promotions
            $key = $lineItem->getPayloadValue('code') ?: $lineItem->getPayloadValue('promotionId');

            if (isset($locks[$key])) {
                // probably multiple discounts of one promotion
                continue;
            }

            $lock = $this->lockFactory->createLock($this->getLockKey($key), self::LOCK_TTL);

            if (!$lock->acquire(true)) {
                throw PromotionException::promotionUsageLocked($key);
            }

            $locks[$key] = $lock;
        }

        if (empty($locks)) {
            return;
        }

        $extension->addExtension(LockExtension::KEY, new LockExtension($locks));
    }

    public function releaseLocks(CheckoutPlaceOrderExtension $extension): void
    {
        $lockExtension = $extension->getExtensionOfType(LockExtension::KEY, LockExtension::class);
        if ($lockExtension === null) {
            return;
        }

        foreach ($lockExtension->locks as $lock) {
            $lock->release();
        }

        $extension->removeExtension(LockExtension::KEY);
    }

    public function getLockKey(string $promotionCodeOrId): string
    {
        return 'promotion-' . $promotionCodeOrId;
    }
}
