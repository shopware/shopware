<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\DTO;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final readonly class SeoUrl
{
    /**
     * @param string $foreignKey the category, product or landing page id
     * @param string $seoPathInfo the actual seo url
     * @param string $pathInfo fallback url
     * @param string|null $id the id of seo_url table
     */
    public function __construct(
        public string $foreignKey,
        public string $seoPathInfo,
        public string $pathInfo,
        public ?string $id = null,
    ) {
    }
}
