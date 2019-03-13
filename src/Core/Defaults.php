<?php declare(strict_types=1);

namespace Shopware\Core;

final class Defaults
{
    public const SALES_CHANNEL = '20080911ffff4fffafffffff19830531';

    public const LANGUAGE_SYSTEM = '20080911ffff4fffafffffff19830531';

    public const LANGUAGE_SYSTEM_DE = '00e84bd18c574a6ca748ac0db17654dc';

    public const SNIPPET_BASE_SET_EN = '71a916e745114d72abafbfdc51cbd9d0';

    public const SNIPPET_BASE_SET_DE = 'b8d2230a7b324e448c9c8b22ed1b89d8';

    public const SNIPPET_AUTHOR = 'Shopware';

    public const LOCALE_SYSTEM = '20080911ffff4fffafffffff19830531';

    public const LOCALE_SYSTEM_DE = '2f3663edb7614308a60188c21c7963d5';

    public const LOCALE_EN_GB_ISO = 'en_GB';

    public const LOCALE_DE_DE_ISO = 'de_DE';

    public const TRANSLATION_CODE_SYSTEM = self::LOCALE_SYSTEM;

    public const FALLBACK_CUSTOMER_GROUP = '20080911ffff4fffafffffff19830531';

    public const LIVE_VERSION = '20080911ffff4fffafffffff19830531';

    public const CURRENCY = '20080911ffff4fffafffffff19830531';

    public const COUNTRY = '20080911ffff4fffafffffff19830531';

    public const SHIPPING_METHOD = '20080911ffff4fffafffffff19830531';

    public const NUMBER_RANGE = '20080911ffff4fffafffffff19830531';

    /**
     * @deprecated
     */
    public const ORDER_STATE_OPEN = '1194a493806742c9b85e61f1f2cf9be8';

    /**
     * @deprecated
     */
    public const ORDER_TRANSACTION_COMPLETED = '60025b03849340ba8d1abf7e58aa2b9f';

    /**
     * @deprecated
     */
    public const ORDER_TRANSACTION_FAILED = 'b64bfc7f379144829365a6994a3b56e6';

    /**
     * @deprecated
     */
    public const ORDER_TRANSACTION_OPEN = '099e79dbfa9f43e4876b172ff58359f2';

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

    public const PAYMENT_METHOD_DEBIT = 'e84976ace9ab4928a3dcc387b66dbaa6';
    public const PAYMENT_METHOD_CASH_ON_DELIVERY = '77573b9cf7914cb5a9519945bff1d95b';
    public const PAYMENT_METHOD_INVOICE = '19d144ffe15f4772860d59fca7f207c1';
    public const PAYMENT_METHOD_PAID_IN_ADVANCE = '47160b00cd064b0188176451f9f3c247';

    public const SALUTATION_KEY_MR = 'mr';
    public const SALUTATION_KEY_MRS = 'mrs';
    public const SALUTATION_KEY_MISS = 'miss';
    public const SALUTATION_KEY_DIVERSE = 'diverse';

    public const SALUTATION_ID_MR = '0ddcfee7912c4f57b7f8560f4fc35e08';
    public const SALUTATION_ID_MRS = '1902982d717a4e97ab371326693d73df';
    public const SALUTATION_ID_MISS = '6fdf0912858540b89a774919593d1b3a';
    public const SALUTATION_ID_DIVERSE = '7ee9f67bf89140629d4a96441c139ff2';

    public const PAYMENT_METHOD_SEPA = 'a6ddadce4cb441f3976a32505049f037';
    public const SALES_CHANNEL_STOREFRONT_API = 'f183ee5650cf4bdb8a774337575067a6';

    public const SALES_CHANNEL_STOREFRONT = '8a243080f92e4c719546314b577cf82b';
    public const DATE_FORMAT = 'Y-m-d H:i:s.v';

    public const NUMBER_RANGE_TYPES = [
        'product' => [
            'id' => '20080911ffff4fffafffffff19830531',
            'global' => 1,
        ],
        'order' => [
            'id' => 'a6ddadce4cb441f3976a32505049f037',
            'global' => 0,
        ],
        'customer' => [
            'id' => '8a243080f92e4c719546314b577cf82b',
            'global' => 0,
        ],
    ];

    public const NUMBER_RANGES = [
        'product' => [
            'id' => '1194a493806742c9b85e61f1f2cf9be8',
            'name' => 'Products',
            'typeId' => '20080911ffff4fffafffffff19830531',
            'pattern' => '{n}',
            'start' => 1,
        ],
        'order' => [
            'id' => '60025b03849340ba8d1abf7e58aa2b9f',
            'name' => 'Orders',
            'typeId' => 'a6ddadce4cb441f3976a32505049f037',
            'pattern' => '{n}',
            'start' => 1,
        ],
        'customer' => [
            'id' => '099e79dbfa9f43e4876b172ff58359f2',
            'name' => 'Customers',
            'typeId' => '8a243080f92e4c719546314b577cf82b',
            'pattern' => '{n}',
            'start' => 1,
        ],
    ];
}
