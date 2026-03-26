<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\RenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class InvoiceRenderData extends RenderData
{
    public function __construct(
        public readonly bool $intraCommunityDelivery,
    ) {
    }
}
