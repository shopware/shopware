<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class UrlResult extends Struct
{
    /**
     * @var Url[]
     */
    private $urls;

    /**
     * @var int|null
     */
    private $nextOffset;

    public function __construct(array $urls, ?int $nextOffset)
    {
        $this->urls = $urls;
        $this->nextOffset = $nextOffset;
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
}
