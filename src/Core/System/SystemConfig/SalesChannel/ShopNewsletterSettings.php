<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Newsletter settings (core.newsletter).
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ShopNewsletterSettings extends Struct
{
    public function __construct(
        public readonly bool $doubleOptIn,
        public readonly bool $doubleOptInRegistered,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_newsletter';
    }
}
