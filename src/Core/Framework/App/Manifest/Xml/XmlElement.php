<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
abstract class XmlElement extends Struct
{
    protected const REQUIRED_FIELDS = [];

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        $this->validateRequiredElements($data, static::REQUIRED_FIELDS);

        foreach ($data as $property => $value) {
            // @phpstan-ignore property.dynamicName (The XML element is abstract dynamic so we allow all dynamic properties)
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): static
    {
        /** @phpstan-ignore new.static,new.staticInAbstractClassStaticMethod (the usage of "new static" is explicitly wanted) */
        return new static(static::parse($element));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        /** @phpstan-ignore new.static,new.staticInAbstractClassStaticMethod (the usage of "new static" is explicitly wanted) */
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

    public static function kebabCaseToCamelCase(string $string): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->denormalize(str_replace('-', '_', $string));
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected static function parse(\DOMElement $element): array;

    /**
     * @see XmlParserUtils::ensureTranslationForLocale()
     *
     * @param array<string, string>|null $translations
     *
     * @return ($translations is null ? null : array<string, string>)
     */
    protected function ensureTranslationForDefaultLanguageExist(?array $translations, string $defaultLocale): ?array
    {
        return XmlParserUtils::ensureTranslationForLocale($translations, $defaultLocale);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $requiredFields
     */
    protected function validateRequiredElements(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw AppException::invalidArgument($field . ' must not be empty');
            }
        }
    }
}
