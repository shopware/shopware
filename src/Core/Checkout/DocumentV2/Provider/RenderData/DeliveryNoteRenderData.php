<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * Render payload for the delivery note. Delivery note ships HTML + PDF only; the HTML template
 * walks the raw `OrderEntity` and reads delivery-note fields from the inherited `custom` bag
 * (`deliveryNoteNumber`, `deliveryDate`, `deliveryNoteDate`), so no extra typed properties are
 * required beyond {@see AbstractRenderData}.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class DeliveryNoteRenderData extends AbstractRenderData
{
}
