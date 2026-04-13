<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
class ShopIdChangedEvent extends Event
{
    public function __construct(
        public readonly ShopId $newShopId,
        public readonly ?ShopId $oldShopId
    ) {
    }
}
