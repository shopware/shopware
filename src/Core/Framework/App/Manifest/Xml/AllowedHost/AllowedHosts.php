<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\AllowedHost;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AllowedHosts extends XmlElement
{
    /**
     * @var list<string>
     */
    protected array $allowedHosts;

    /**
     * @return list<string>
     */
    public function getHosts(): array
    {
        return $this->allowedHosts;
    }

    protected static function parse(\DOMElement $element): array
    {
        $allowedHosts = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $allowedHosts[] = $child->nodeValue;
        }

        return ['allowedHosts' => $allowedHosts];
    }
}
