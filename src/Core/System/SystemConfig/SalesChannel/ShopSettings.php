<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Sales-channel-resolved subset of the system configuration that headless
 * frontends need to render the shop consistently with the administration
 * settings. Only UI- and validation-relevant, non-sensitive values are exposed.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ShopSettings extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        public readonly ShopGeneralSettings $general,
        public readonly ShopContactFormSettings $contactForm,
        public readonly ShopLoginRegistrationSettings $loginRegistration,
        public readonly ShopCartSettings $cart,
        public readonly ShopListingSettings $listing,
        public readonly ShopNewsletterSettings $newsletter,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'shop_settings';
    }
}
