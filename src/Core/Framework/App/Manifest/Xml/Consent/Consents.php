<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Consent;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class Consents extends XmlElement
{
    /**
     * @var list<Consent>
     */
    protected array $consents = [];

    /**
     * @return list<Consent>
     */
    public function getConsents(): array
    {
        return $this->consents;
    }

    protected static function parse(\DOMElement $element): array
    {
        $consents = [];
        foreach ($element->getElementsByTagName('consent') as $consent) {
            $consents[] = Consent::fromXml($consent);
        }

        return ['consents' => $consents];
    }
}
