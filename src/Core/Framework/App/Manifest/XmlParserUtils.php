<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class XmlParserUtils
{
    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @return array<string, mixed>
     */
    public static function parseAttributes(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            $values[self::kebabCaseToCamelCase($attribute->name)] = XmlReader::phpize($attribute->value);
        }

        return $values;
    }

    /**
     * @return array<string, string|null>
     */
    public static function parseChildren(\DOMElement $element, ?callable $transformer = null): array
    {
        $values = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values[self::kebabCaseToCamelCase($child->tagName)] = $transformer ? $transformer($child) : XmlReader::phpize($child->nodeValue);
        }

        return $values;
    }

    /**
     * @return array<int, string|null>
     */
    public static function parseChildrenAsList(\DOMElement $element, ?callable $transformer = null): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values[] = $transformer ? $transformer($child) : XmlReader::phpize($child->nodeValue);
        }

        return $values;
    }

    /**
     * @param list<string> $translatableFields
     *
     * @return array<string, string|array<string, string>>
     */
    public static function parseChildrenAndTranslate(\DOMElement $element, array $translatableFields): array
    {
        $values = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (\in_array($child->tagName, $translatableFields, true)) {
                $values = self::mapTranslatedTag($child, $values);

                continue;
            }

            $values[self::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public static function mapTranslatedTag(\DOMElement $element, array $values): array
    {
        $tagName = static::kebabCaseToCamelCase($element->tagName);

        if (!\array_key_exists($tagName, $values)) {
            $values[$tagName] = [];
        }

        $values[$tagName][self::getLocaleCodeFromElement($element)] = trim($element->nodeValue ?? '');

        return $values;
    }

    public static function kebabCaseToCamelCase(string $string): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->denormalize(str_replace('-', '_', $string));
    }

    private static function getLocaleCodeFromElement(\DOMElement $element): string
    {
        return $element->getAttribute('lang') ?: self::FALLBACK_LOCALE;
    }
}
