<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Media\Extension;

use Shopware\Core\Framework\Struct\Struct;

class MediaLinksStruct extends Struct
{
    /**
     * @var string
     */
    protected $url;

    protected $thumbnailUrls;

    public function __construct(string $url, array $thumbnailUrls = [])
    {
        $this->url = $url;
        $this->thumbnailUrls = $thumbnailUrls;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getThumbnailUrls(): array
    {
        return $this->thumbnailUrls;
    }

    public function setThumbnailUrls(array $thumbnailUrls): void
    {
        $this->thumbnailUrls = $thumbnailUrls;
    }
}
