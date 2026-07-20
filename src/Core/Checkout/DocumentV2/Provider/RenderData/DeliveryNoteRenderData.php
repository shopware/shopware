<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * Render payload for the delivery note. Delivery note ships HTML + PDF only; the HTML template
 * walks the raw `OrderEntity` and reads delivery-note fields from the `custom` bag
 * (`deliveryNoteNumber`, `deliveryDate`, `deliveryNoteDate`). Cross-cutting data (config, company,
 * display, document identity) lives in {@see DocumentMetaRenderData}.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class DeliveryNoteRenderData extends AbstractRenderData
{
    /**
     * @param array<string, mixed> $custom
     */
    public function __construct(
        /**
         * @deprecated tag:v6.8.0 - feeds the legacy flat `config.custom.*` template contract
         */
        public array $custom = [],
    ) {
    }
}
