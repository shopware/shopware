<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Tax;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Tax extends XmlElement
{
    /**
     * @var list<TaxProvider>
     */
    protected array $taxProviders = [];

    /**
     * @return list<TaxProvider>
     */
    public function getTaxProviders(): array
    {
        return $this->taxProviders;
    }

    /**
     * @return array<string>
     */
    public function getUrls(): array
    {
        return \array_map(fn (TaxProvider $taxProvider) => $taxProvider->getProcessUrl(), $this->taxProviders);
    }

    protected static function parse(\DOMElement $element): array
    {
        $taxProviders = [];

        foreach ($element->getElementsByTagName('tax-provider') as $taxProvider) {
            $taxProviders[] = TaxProvider::fromXml($taxProvider);
        }

        return ['taxProviders' => $taxProviders];
    }
}
