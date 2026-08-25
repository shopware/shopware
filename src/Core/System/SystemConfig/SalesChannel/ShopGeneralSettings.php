<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Shop identity and meta defaults (core.basicInformation).
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ShopGeneralSettings extends Struct
{
    use ConfigCastTrait;

    /**
     * @internal
     */
    public function __construct(
        public readonly string $shopName,
        public readonly string $metaAuthor,
        public readonly string $metaRobots,
        public readonly bool $familyFriendly,
        public readonly bool $showRevocationButton,
    ) {
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $config The values of the core.basicInformation config domain
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            shopName: self::stringValue($config, 'shopName'),
            metaAuthor: self::stringValue($config, 'metaAuthor'),
            metaRobots: self::stringValue($config, 'metaRobots'),
            familyFriendly: self::boolValue($config, 'familyFriendly'),
            showRevocationButton: self::boolValue($config, 'showRevocationButton'),
        );
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_general';
    }
}
