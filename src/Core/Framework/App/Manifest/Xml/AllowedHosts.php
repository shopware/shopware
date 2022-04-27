<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AllowedHosts extends XmlElement
{
    protected array $allowedHosts;

    private function __construct(array $allowedHosts)
    {
        $this->allowedHosts = $allowedHosts;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseAllowedHosts($element));
    }

    public static function fromArray(array $allowedHosts): self
    {
        return new self($allowedHosts);
    }

    public function getHosts(): array
    {
        return $this->allowedHosts;
    }

    private static function parseAllowedHosts(\DOMElement $element): array
    {
        $allowedHosts = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $allowedHosts[] = $child->nodeValue;
        }

        return $allowedHosts;
    }
}
