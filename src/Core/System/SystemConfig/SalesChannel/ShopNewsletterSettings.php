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
    use ConfigCastTrait;

    /**
     * @internal
     */
    public function __construct(
        public readonly bool $doubleOptIn,
        public readonly bool $doubleOptInRegistered,
    ) {
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $config The values of the core.newsletter config domain
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            doubleOptIn: self::boolValue($config, 'doubleOptIn'),
            doubleOptInRegistered: self::boolValue($config, 'doubleOptInRegistered'),
        );
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_newsletter';
    }
}
