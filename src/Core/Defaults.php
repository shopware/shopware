<?php declare(strict_types=1);

namespace Shopware\Core;

final class Defaults
{
    public const SALES_CHANNEL = '20080911ffff4fffafffffff19830531';

    public const LANGUAGE_SYSTEM = '20080911ffff4fffafffffff19830531';

    public const LANGUAGE_SYSTEM_DE = '00e84bd18c574a6ca748ac0db17654dc';

    public const SNIPPET_BASE_SET_EN = '71a916e745114d72abafbfdc51cbd9d0';

    public const SNIPPET_BASE_SET_DE = 'b8d2230a7b324e448c9c8b22ed1b89d8';

    public const LOCALE_SYSTEM = '20080911ffff4fffafffffff19830531';

    public const LOCALE_SYSTEM_DE = '2f3663edb7614308a60188c21c7963d5';

    public const LOCALE_EN_GB_ISO = 'en_GB';

    public const LOCALE_DE_DE_ISO = 'de_DE';

    public const FALLBACK_CUSTOMER_GROUP = '20080911ffff4fffafffffff19830531';

    public const LIVE_VERSION = '20080911ffff4fffafffffff19830531';

    public const CURRENCY = '20080911ffff4fffafffffff19830531';

    public const COUNTRY = '20080911ffff4fffafffffff19830531';

    public const SHIPPING_METHOD = '20080911ffff4fffafffffff19830531';

    public const ORDER_STATE_MACHINE = 'order.state';
    public const ORDER_STATE_STATES_OPEN = 'open';
    public const ORDER_STATE_STATES_IN_PROGRESS = 'in_progress';
    public const ORDER_STATE_STATES_COMPLETED = 'completed';
    public const ORDER_STATE_STATES_CANCELLED = 'cancelled';

    public const ORDER_DELIVERY_STATE_MACHINE = 'order_delivery.state';
    public const ORDER_DELIVERY_STATES_OPEN = 'open';
    public const ORDER_DELIVERY_STATES_PARTIALLY_SHIPPED = 'shipped_partially';
    public const ORDER_DELIVERY_STATES_SHIPPED = 'shipped';
    public const ORDER_DELIVERY_STATES_RETURNED = 'returned';
    public const ORDER_DELIVERY_STATES_PARTIALLY_RETURNED = 'returned_partially';
    public const ORDER_DELIVERY_STATES_CANCELLED = 'cancelled';

    public const ORDER_TRANSACTION_STATE_MACHINE = 'order_transaction.state';
    public const ORDER_TRANSACTION_STATES_OPEN = 'open';
    public const ORDER_TRANSACTION_STATES_PAID = 'paid';
    public const ORDER_TRANSACTION_STATES_PARTIALLY_PAID = 'paid_partially';
    public const ORDER_TRANSACTION_STATES_REFUNDED = 'refunded';
    public const ORDER_TRANSACTION_STATES_PARTIALLY_REFUNDED = 'refunded_partially';
    public const ORDER_TRANSACTION_STATES_CANCELLED = 'cancelled';
    public const ORDER_TRANSACTION_STATES_REMINDED = 'reminded';

    public const SALUTATION_ID_MR = '0ddcfee7912c4f57b7f8560f4fc35e08';
    public const SALUTATION_ID_MRS = '1902982d717a4e97ab371326693d73df';
    public const SALUTATION_ID_MISS = '6fdf0912858540b89a774919593d1b3a';
    public const SALUTATION_ID_DIVERSE = '7ee9f67bf89140629d4a96441c139ff2';

    public const SALES_CHANNEL_STOREFRONT_API = 'f183ee5650cf4bdb8a774337575067a6';

    public const SALES_CHANNEL_STOREFRONT = '8a243080f92e4c719546314b577cf82b';
    public const DATE_FORMAT = 'Y-m-d H:i:s.v';
}
