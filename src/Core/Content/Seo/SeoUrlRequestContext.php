<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
final readonly class SeoUrlRequestContext
{
    public function __construct(
        public string $languageId,
        public string $salesChannelId,
        public string $pathInfo,
        public ?string $queryString = null,
    ) {
    }
}
