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

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
}
