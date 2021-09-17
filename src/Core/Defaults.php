<?php declare(strict_types=1);

namespace Shopware\Core;

use Shopware\Core\Test\TestDefaults;

/**
 * @Internal
 * System wide defaults that are fixed for performance measures
 */
final class Defaults
{
    /**
     * @deprecated tag:v6.5.0 - Don't rely on this id, the headless sales channel could be deleted.
     */
    public const SALES_CHANNEL = TestDefaults::SALES_CHANNEL;

    /**
     * Don't depend on this being en-GB, the underlying language can be overwritten by the installer!
     */
    public const LANGUAGE_SYSTEM = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

    /**
     * @deprecated tag:v6.5.0 - Use sales channel default customer group instead
     */
    public const FALLBACK_CUSTOMER_GROUP = TestDefaults::FALLBACK_CUSTOMER_GROUP;

    public const LIVE_VERSION = '0fa91ce3e96a4bc2be4bd9ce752c3425';

    /**
     * Don't depend on this being EUR, the underlying currency can be overwritten by the installer!
     */
    public const CURRENCY = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';

    public const SALES_CHANNEL_TYPE_API = 'f183ee5650cf4bdb8a774337575067a6';

    public const SALES_CHANNEL_TYPE_STOREFRONT = '8a243080f92e4c719546314b577cf82b';

    public const SALES_CHANNEL_TYPE_PRODUCT_COMPARISON = 'ed535e5722134ac1aa6524f73e26881b';

    public const STORAGE_DATE_TIME_FORMAT = 'Y-m-d H:i:s.v';

    /**
     * Do not use STORAGE_DATE_FORMAT for createdAt fields, use STORAGE_DATE_TIME_FORMAT instead
     */
    public const STORAGE_DATE_FORMAT = 'Y-m-d';

    public const CMS_PRODUCT_DETAIL_PAGE = '7a6d253a67204037966f42b0119704d5';

    /**
     * @deprecated tag:v6.5.0 This will be superfluous in future updates, as the salutation will be completely optional.
     * This constant is only needed to ensure extensions always get a default salutation for customer/address entities
     * via the `CustomerDefaultSalutationSubscriber`.
     */
    public const SALUTATION = 'ed643807c9f84cc8b50132ea3ccb1c3b';
}
