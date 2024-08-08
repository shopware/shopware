<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

trait UrlXmlElement
{
    protected ?string $url = null;

    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @return array{url: string|null}
     */
    protected static function parse(\DOMElement $element): array
    {
        return ['url' => $element->nodeValue];
    }
}
