<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class XmlParserUtils
{
    private const FALLBACK_LOCALE = 'en-GB';

    public static function loadFile(string $xmlFile, string $xsdFile): \DOMDocument
    {
        try {
            return XmlUtils::loadFile($xmlFile, $xsdFile);
        } catch (\Exception $e) {
            throw AppException::xmlParsingException($xmlFile, $e->getMessage());
        }
    }

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
     * @return list<string|null>
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
     * @return array<string, string>
     */
    public static function parseTranslations(\DOMElement $element, string $tagName): array
    {
        $translations = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->tagName !== $tagName) {
                continue;
            }

            $translations[self::getLocaleCodeFromElement($child)] = trim($child->nodeValue ?? '');
        }

        return $translations;
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

    /**
     * Adds the translation for the locale if it is missing, copied from the closest declared translation:
     * the main region of the same language (e.g. de-DE for de-AT), any other region of that language,
     * en-GB, and finally the first translation.
     *
     * @param array<string, string>|null $translations
     *
     * @return ($translations is null ? null : array<string, string>)
     */
    public static function ensureTranslationForLocale(?array $translations, string $locale): ?array
    {
        if ($translations === null || $translations === []) {
            return $translations;
        }

        $declaredLocales = [];
        foreach (array_keys($translations) as $declaredLocale) {
            $declaredLocales[mb_strtolower($declaredLocale)] ??= $declaredLocale;
        }

        if (isset($declaredLocales[mb_strtolower($locale)])) {
            return $translations;
        }

        $fallbackLocale = self::findLocaleOfSameLanguage($declaredLocales, $locale)
            ?? $declaredLocales[mb_strtolower(self::FALLBACK_LOCALE)]
            ?? array_key_first($translations);

        $translations[$locale] = $translations[$fallbackLocale];

        return $translations;
    }

    public static function kebabCaseToCamelCase(string $string): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->denormalize(str_replace('-', '_', $string));
    }

    private static function getLocaleCodeFromElement(\DOMElement $element): string
    {
        return $element->getAttribute('lang') ?: self::FALLBACK_LOCALE;
    }

    /**
     * @param array<string, string> $declaredLocales declared locales, keyed by their lowercase form
     */
    private static function findLocaleOfSameLanguage(array $declaredLocales, string $locale): ?string
    {
        $language = self::getLanguage($locale);
        if ($language === null) {
            return null;
        }

        $mainRegion = $language . '-' . $language;
        if (isset($declaredLocales[$mainRegion])) {
            return $declaredLocales[$mainRegion];
        }

        foreach ($declaredLocales as $lowercaseLocale => $declaredLocale) {
            if (self::getLanguage($lowercaseLocale) === $language) {
                return $declaredLocale;
            }
        }

        return null;
    }

    private static function getLanguage(string $locale): ?string
    {
        if (preg_match('/^([a-z]{2,3})(?:[-_]|$)/i', $locale, $matches) !== 1) {
            return null;
        }

        return mb_strtolower($matches[1]);
    }
}
