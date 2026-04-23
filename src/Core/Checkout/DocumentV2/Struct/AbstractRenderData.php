<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * Base class for provider-specific DTOs stored in RenderInput.
 *
 * Each document data provider returns its own AbstractRenderData subtype so renderers can consume
 * typed, precomputed input instead of reaching back into the data loading layer.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
abstract readonly class AbstractRenderData
{
}
