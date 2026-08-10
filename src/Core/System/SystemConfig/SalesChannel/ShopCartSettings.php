<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Cart and checkout settings (core.cart).
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ShopCartSettings extends Struct
{
    public function __construct(
        public readonly int $maxQuantity,
        public readonly int $lineItemAddLimit,
        public readonly bool $showDeliveryTime,
        public readonly bool $showSubtotal,
        public readonly bool $columnTaxInsteadUnitPrice,
        public readonly bool $showCustomerComment,
        public readonly bool $showTosCheckbox,
        public readonly bool $openOffcanvasAfterAddToCart,
        public readonly bool $wishlistEnabled,
        public readonly bool $logoutGuestAfterCheckout,
        public readonly bool $enableOrderRefunds,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_cart';
    }
}
