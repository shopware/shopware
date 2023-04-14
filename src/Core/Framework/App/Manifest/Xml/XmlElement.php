<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class XmlElement extends Struct
{
    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array
    {
        $array = get_object_vars($this);

        unset($array['extensions']);

        return $array;
    }

    /**
     * @param array<int|string, mixed> $values
     *
     * @return array<int|string, mixed>
     */
    protected static function mapTranslatedTag(\DOMElement $child, array $values): array
    {
        if (!\array_key_exists(self::kebabCaseToCamelCase($child->tagName), $values)) {
            $values[self::kebabCaseToCamelCase($child->tagName)] = [];
        }

        // psalm would fail if it can't infer type from nested array
        /** @var array<string, string> $tagValues */
        $tagValues = $values[self::kebabCaseToCamelCase($child->tagName)];
        $tagValues[self::getLocaleCodeFromElement($child)] = trim($child->nodeValue ?? '');
        $values[self::kebabCaseToCamelCase($child->tagName)] = $tagValues;

        return $values;
    }

    /**
     * @param callable(\DOMElement): (XmlElement|string) $transformer
     *
     * @return array<mixed>
     */
    protected static function parseChildNodes(\DOMElement $child, callable $transformer): array
    {
        $values = [];
        foreach ($child->childNodes as $field) {
            if (!$field instanceof \DOMElement) {
                continue;
            }

            $values[] = $transformer($field);
        }

        return $values;
    }

    protected static function kebabCaseToCamelCase(string $string): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->denormalize(str_replace('-', '_', $string));
    }

    /**
     * if translations for system default language are not provided it tries to use the english translation as the default,
     * if english does not exist it uses the first translation
     *
     * @param array<string, string> $translations
     *
     * @return array<string, string>
     */
    protected function ensureTranslationForDefaultLanguageExist(array $translations, string $defaultLocale): array
    {
        if (empty($translations)) {
            return $translations;
        }

        if (!\array_key_exists($defaultLocale, $translations)) {
            $translations[$defaultLocale] = $this->getFallbackTranslation($translations);
        }

        return $translations;
    }

    /**
     * @param array<int|string, mixed> $data
     * @param array<int|string, string> $requiredFields
     */
    protected function validateRequiredElements(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException($field . ' must not be empty');
            }
        }
    }

    private static function getLocaleCodeFromElement(\DOMElement $element): string
    {
        return $element->getAttribute('lang') ?: self::FALLBACK_LOCALE;
    }

    /**
     * @param array<string, string> $translations
     */
    private function getFallbackTranslation(array $translations): string
    {
        if (\array_key_exists(self::FALLBACK_LOCALE, $translations)) {
            return $translations[self::FALLBACK_LOCALE];
        }

        return array_values($translations)[0];
    }
}
