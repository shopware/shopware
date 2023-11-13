<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-consistent-constructor
 */
#[Package('core')]
abstract class XmlElement extends Struct
{
    protected const REQUIRED_FIELDS = [];
    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        $this->validateRequiredElements($data, static::REQUIRED_FIELDS);

        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): static
    {
        return new static(static::parse($element));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

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
     * @return array<string, mixed>
     */
    abstract protected static function parse(\DOMElement $element): array;

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    protected static function mapTranslatedTag(\DOMElement $child, array $values): array
    {
        if (!\array_key_exists(static::kebabCaseToCamelCase($child->tagName), $values)) {
            $values[static::kebabCaseToCamelCase($child->tagName)] = [];
        }

        $tagValues = $values[static::kebabCaseToCamelCase($child->tagName)];
        $tagValues[self::getLocaleCodeFromElement($child)] = trim($child->nodeValue ?? '');
        $values[static::kebabCaseToCamelCase($child->tagName)] = $tagValues;

        return $values;
    }

    /**
     * @template TReturn of XmlElement|string
     *
     * @param callable(\DOMElement): TReturn $transformer
     *
     * @return list<TReturn>
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
     * @param array<string, mixed> $data
     * @param list<string> $requiredFields
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
