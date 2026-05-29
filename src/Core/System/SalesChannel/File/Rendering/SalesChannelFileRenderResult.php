<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Rendering;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class SalesChannelFileRenderResult
{
    public function __construct(
        private string $fileName,
        private string $content,
        private string $contentType,
    ) {
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }
}
