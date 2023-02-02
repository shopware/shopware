<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface ProductAware extends FlowEventAware
{
    public const PRODUCT = 'product';

    public const PRODUCT_ID = 'productId';

    public function getProductId(): string;
}
