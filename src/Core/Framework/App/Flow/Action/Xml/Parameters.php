<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Flow\Action\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class Parameters extends XmlElement
{
    /**
     * @var list<Parameter>
     */
    protected array $parameters;

    /**
     * @return list<Parameter>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->getElementsByTagName('parameter') as $parameter) {
            $values[] = Parameter::fromXml($parameter);
        }

        return ['parameters' => $values];
    }
}
