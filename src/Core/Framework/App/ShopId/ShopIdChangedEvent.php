<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @phpstan-import-type ShopId from ShopIdProvider
 */
#[Package('core')]
class ShopIdChangedEvent extends Event
{
    /**
     * @param ShopId $newShopId
     * @param ShopId|null $oldShopId
     */
    public function __construct(
        public readonly array $newShopId,
        public readonly ?array $oldShopId
    ) {
    }
}
