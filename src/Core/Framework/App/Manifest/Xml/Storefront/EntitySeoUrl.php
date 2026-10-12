<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Storefront;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class EntitySeoUrl extends XmlElement
{
    protected const REQUIRED_FIELDS = ['name', 'entity', 'defaultTemplate'];

    protected string $name;

    protected ?string $hook = null;

    protected string $entity;

    protected string $defaultTemplate;

    public function getName(): string
    {
        return $this->name;
    }

    public function getHook(): string
    {
        return $this->hook ?? $this->name;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getDefaultTemplate(): string
    {
        return $this->defaultTemplate;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [
            'name' => $element->getAttribute('name'),
            'entity' => $element->getAttribute('entity'),
        ];

        if ($element->hasAttribute('hook')) {
            $values['hook'] = $element->getAttribute('hook');
        }

        $defaultTemplate = $element->getElementsByTagName('default-template')->item(0);
        if ($defaultTemplate !== null) {
            $values['defaultTemplate'] = trim((string) $defaultTemplate->nodeValue);
        }

        return $values;
    }
}
