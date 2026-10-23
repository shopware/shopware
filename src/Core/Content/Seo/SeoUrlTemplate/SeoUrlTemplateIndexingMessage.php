<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Dispatched when a `seo_url_template` row changes its `template` field, so the
 * corresponding SEO URLs are regenerated asynchronously and large catalog
 * iteration never blocks the admin save.
 *
 * Each message covers a single batch of entities; the handler dispatches a
 * follow-up message carrying the iterator offset until the whole entity set is
 * processed, so a single message never exceeds worker time limits.
 *
 * @internal
 */
#[Package('inventory')]
class SeoUrlTemplateIndexingMessage implements AsyncMessageInterface
{
    /**
     * @param array{offset: int|null}|null $offset
     * @param bool $onlyChangedTemplate regenerates only the sales channels that use the changed template:
     *                                  the own template of `$templateSalesChannelId`, or the default template when it is `null`
     */
    public function __construct(
        public readonly string $routeName,
        public readonly string $entityName,
        public readonly ?array $offset = null,
        public readonly bool $onlyChangedTemplate = false,
        public readonly ?string $templateSalesChannelId = null,
    ) {
    }
}
