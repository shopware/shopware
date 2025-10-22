<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Cookie;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class Cookies extends XmlElement
{
    private const NAME_TAG = 'snippet-name';
    private const DESCRIPTION_TAG = 'snippet-description';
    private const COOKIE_TAG = 'cookie';
    private const VALUE_TAG = 'value';
    private const EXPIRATION_TAG = 'expiration';
    private const ENTRIES_TAG = 'entries';
    private const DEFAULT_TARGET_GROUP_ATTR = 'default-target-group';
    private const TARGET_GROUP_ATTR = 'target-group';

    /**
     * @var list<array<string, mixed>>
     */
    protected array $cookies = [];

    protected ?string $defaultTargetGroup = null;

    /**
     * @return list<array<string, mixed>>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function getDefaultTargetGroup(): ?string
    {
        return $this->defaultTargetGroup;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];
        $defaultTargetGroup = null;

        // Parse default-target-group attribute from cookies element
        if ($element->hasAttribute(self::DEFAULT_TARGET_GROUP_ATTR)) {
            $defaultTargetGroup = $element->getAttribute(self::DEFAULT_TARGET_GROUP_ATTR);
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values = self::parseChild($child, $values);
        }

        return [
            'cookies' => $values,
            'defaultTargetGroup' => $defaultTargetGroup !== '' ? $defaultTargetGroup : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $values
     *
     * @return list<array<string, mixed>>
     */
    private static function parseChild(\DOMElement $element, array $values): array
    {
        $cookie = [];

        // Parse target-group attribute from group element
        if ($element->hasAttribute(self::TARGET_GROUP_ATTR)) {
            $targetGroup = $element->getAttribute(self::TARGET_GROUP_ATTR);
            if ($targetGroup !== '') {
                $cookie['target_group'] = $targetGroup;
            }
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (\in_array($child->tagName, [self::NAME_TAG, self::DESCRIPTION_TAG, self::COOKIE_TAG, self::VALUE_TAG, self::EXPIRATION_TAG], true)) {
                $cookie[self::kebabCaseToSnakeCase($child->tagName)] = $child->nodeValue;
            }

            if ($child->tagName === self::ENTRIES_TAG) {
                $cookie[self::ENTRIES_TAG] = self::parse($child)['cookies'];
            }
        }

        $values[] = $cookie;

        return $values;
    }

    private static function kebabCaseToSnakeCase(string $str): string
    {
        return lcfirst(str_replace('-', '_', $str));
    }
}
