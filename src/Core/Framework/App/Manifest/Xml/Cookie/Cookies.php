<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Cookie;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Cookies extends XmlElement
{
    private const NAME_TAG = 'snippet-name';
    private const DESCRIPTION_TAG = 'snippet-description';
    private const COOKIE_TAG = 'cookie';
    private const VALUE_TAG = 'value';
    private const EXPIRATION_TAG = 'expiration';
    private const ENTRIES_TAG = 'entries';

    /**
     * @var list<array<string, mixed>>
     */
    protected array $cookies = [];

    /**
     * @return list<array<string, mixed>>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values = self::parseChild($child, $values);
        }

        return ['cookies' => $values];
    }

    /**
     * @param list<array<string, mixed>> $values
     *
     * @return list<array<string, mixed>>
     */
    private static function parseChild(\DOMElement $element, array $values): array
    {
        $cookie = [];

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
