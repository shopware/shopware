<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

/**
 * @internal only for use by the app-system
 */
class Tax extends XmlElement
{
    /**
     * @var TaxProvider[]
     */
    protected array $taxProviders = [];

    /**
     * @param array<TaxProvider> $taxProviders
     */
    private function __construct(array $taxProviders)
    {
        $this->taxProviders = $taxProviders;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseTaxProviders($element));
    }

    /**
     * @return TaxProvider[]
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

    /**
     * @return TaxProvider[]
     */
    private static function parseTaxProviders(\DOMElement $element): array
    {
        $taxProviders = [];

        foreach ($element->getElementsByTagName('tax-provider') as $taxProvider) {
            $taxProviders[] = TaxProvider::fromXml($taxProvider);
        }

        return $taxProviders;
    }
}
