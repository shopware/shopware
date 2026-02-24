<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\ContentSystem;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * Pairs a content layout assignable definition with Storefront-specific
 * SEO metadata that doesn't belong in the Core definition.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
final readonly class ContentSeoRouteDescriptor
{
    public function __construct(
        public AbstractContentLayoutAssignableDefinition $definition,
        public string $legacySeoRouteName,
    ) {
    }
}
