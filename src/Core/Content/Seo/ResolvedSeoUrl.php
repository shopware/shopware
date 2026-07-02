<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
final readonly class ResolvedSeoUrl
{
    public function __construct(
        public string $pathInfo,
        public bool $isCanonical,
        public ?string $id = null,
        public ?string $canonicalPathInfo = null,
        public ?string $seoPathInfo = null,
    ) {
    }
}
