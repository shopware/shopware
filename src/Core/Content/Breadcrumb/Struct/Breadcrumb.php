<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @experimental stableVersion:v6.7.0 feature:BREADCRUMB_STORE_API
 */
#[Package('inventory')]
class Breadcrumb extends Struct
{
    /**
     * @param array<string, mixed> $translated
     * @param array<int, array<string, string>> $seoUrls
     */
    public function __construct(
        public string $name,
        public string $categoryId = '',
        public string $type = '',
        public array $translated = [],
        public string $path = '',
        public array $seoUrls = []
    ) {
    }

    public function getApiAlias(): string
    {
        return 'breadcrumb';
    }
}
