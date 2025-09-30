<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class CheckoutPermissions
{
    /**
     * Skip persisting the cart
     */
    final public const SKIP_CART_PERSISTENCE = 'skipCartPersistence';

    final public const PERSIST_CART_ERRORS = 'persist-cart-errors';

    final public const ALLOW_PRODUCT_PRICE_OVERWRITES = 'allowProductPriceOverwrites';

    final public const ALLOW_PRODUCT_LABEL_OVERWRITES = 'allowProductLabelOverwrites';

    final public const SKIP_PRODUCT_RECALCULATION = 'skipProductRecalculation';

    final public const SKIP_PRODUCT_STOCK_VALIDATION = 'skipProductStockValidation';

    final public const KEEP_INACTIVE_PRODUCT = 'keepInactiveProduct';

    final public const SKIP_DELIVERY_PRICE_RECALCULATION = 'skipDeliveryPriceRecalculation';

    final public const SKIP_DELIVERY_TAX_RECALCULATION = 'skipDeliveryTaxRecalculation';

    /**
     * Existing set of promotions will not be changed.
     * Promotions may **not** be recalculated based on their price definition.
     *
     * Takes precedence over {@see self::PIN_MANUAL_PROMOTIONS} and {@see self::PIN_MANUAL_PROMOTIONS}.
     */
    final public const SKIP_PROMOTION = 'skipPromotion';

    /**
     * Skips the addition of automatic promotion.
     * If {@see self::PIN_AUTOMATIC_PROMOTIONS} is not set, all existing automatic promotions will be deleted.
     */
    final public const SKIP_AUTOMATIC_PROMOTIONS = 'skipAutomaticPromotions';

    /**
     * Existing set of manual/fixed promotions will not be changed,
     * but new manual/fixed promotions can be added.
     * Promotions may be recalculated based on their price definition.
     */
    final public const PIN_MANUAL_PROMOTIONS = 'pinManualPromotions';

    /**
     * Existing set of automatic promotions will not be changed.
     * Promotions may be recalculated based on their price definition.
     *
     * Takes precedence over {@see self::SKIP_AUTOMATIC_PROMOTIONS}.
     */
    final public const PIN_AUTOMATIC_PROMOTIONS = 'pinAutomaticPromotions';

    /**
     * Adds deletion notices for automatic promotions too.
     */
    final public const AUTOMATIC_PROMOTION_DELETION_NOTICES = 'automaticPromotionDeletionNotices';

    /**
     * Skips overwritting the primary order transaction and order delivery ids in {@see OrderConverter::convertToOrder}
     */
    final public const SKIP_PRIMARY_ORDER_IDS = 'skipPrimaryOrderIds';

    private function __construct()
    {
    }
}
