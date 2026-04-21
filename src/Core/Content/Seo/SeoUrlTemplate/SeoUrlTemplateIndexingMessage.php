<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Dispatched when a `seo_url_template` row changes its `template` field, so the
 * corresponding SEO URLs are regenerated asynchronously and large catalog
 * iteration never blocks the admin save.
 *
 * @internal
 */
#[Package('inventory')]
class SeoUrlTemplateIndexingMessage implements AsyncMessageInterface
{
    public function __construct(
        public readonly string $routeName,
        public readonly string $entityName,
    ) {
    }
}
