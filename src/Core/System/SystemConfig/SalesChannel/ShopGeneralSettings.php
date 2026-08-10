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
    public function __construct(
        public readonly string $shopName,
        public readonly string $metaAuthor,
        public readonly string $metaRobots,
        public readonly bool $familyFriendly,
        public readonly bool $firstNameFieldRequired,
        public readonly bool $lastNameFieldRequired,
        public readonly bool $phoneNumberFieldRequired,
        public readonly bool $showRevocationButton,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_general';
    }
}
