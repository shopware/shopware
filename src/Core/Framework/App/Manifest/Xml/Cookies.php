<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class Cookies extends XmlElement
{
    public const NAME_TAG = 'snippet-name';
    public const DESCRIPTION_TAG = 'snippet-description';
    public const COOKIE_TAG = 'cookie';
    public const VALUE_TAG = 'value';
    public const EXPIRATION_TAG = 'expiration';
    public const ENTRIES_TAG = 'entries';

    /**
     * @var Cookies[]
     */
    protected $cookies = [];

    private function __construct(array $cookies)
    {
        $this->cookies = $cookies;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseCookies($element));
    }

    /**
     * @return Cookies[]
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * @return Cookies[]
     */
    private static function parseCookies(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values = self::parseChild($child, $values);
        }

        return $values;
    }

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
                $cookie[self::ENTRIES_TAG] = self::parseCookies($child);
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
