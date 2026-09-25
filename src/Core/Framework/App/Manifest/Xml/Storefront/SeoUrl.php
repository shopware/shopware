<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Storefront;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class SeoUrl extends XmlElement
{
    protected const REQUIRED_FIELDS = ['name'];

    protected string $name;

    protected ?string $hook = null;

    /**
     * @var array<string, string>
     */
    protected array $path = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function getHook(): string
    {
        return $this->hook ?? $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * @return array{name: string, hook: string, path: array<string, string>}
     */
    public function toArray(string $defaultLocale): array
    {
        return [
            'name' => $this->name,
            'hook' => $this->getHook(),
            'path' => $this->ensureTranslationForDefaultLanguageExist($this->path, $defaultLocale),
        ];
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = ['name' => $element->getAttribute('name')];

        if ($element->hasAttribute('hook')) {
            $values['hook'] = $element->getAttribute('hook');
        }

        return $values + XmlParserUtils::parseChildrenAndTranslate($element, ['path']);
    }
}
