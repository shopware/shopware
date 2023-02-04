<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('sales-channel')]
class UrlResult extends Struct
{
    /**
     * @param Url[] $urls
     */
    public function __construct(
        private readonly array $urls,
        private readonly ?int $nextOffset
    ) {
    }

    /**
     * @return Url[]
     */
    public function getUrls(): array
    {
        return $this->urls;
    }

    public function getNextOffset(): ?int
    {
        return $this->nextOffset;
    }

    public function getApiAlias(): string
    {
        return 'sitemap_url_result';
    }
}
