<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * Marker base for provider-specific render-data DTOs stored in {@see RenderInput}.
 *
 * Each document data provider returns its own subtype so renderers can consume typed, precomputed
 * input instead of reaching back into the data loading layer. The base holds no state: shared data
 * lives in {@see \Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData}
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer\DocumentRendererSnapshotTest
 */
#[Package('after-sales')]
abstract readonly class AbstractRenderData
{
}
