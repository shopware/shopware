<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
interface ShopNameAware extends FlowEventAware
{
    public const SHOP_NAME = 'shopName';

    public function getShopName(): string;
}
