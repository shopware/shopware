<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(XmlParserUtils::class)]
class XmlParserUtilsTest extends TestCase
{
    public function testParseAttributes(): void
    {
        $element = $this->createDOMElement(['attr1' => 'value1', 'attr_2' => 'value2']);

        $result = XmlParserUtils::parseAttributes($element);

        static::assertSame(['attr1' => 'value1', 'attr2' => 'value2'], $result);
    }

    public function testParseAttributesPhpizesValueEvenWhenTypeIsString(): void
    {
        $element = $this->createDOMElement([
            'type' => 'string',
            'value' => '{"foo":"bar"}',
        ]);

        $result = XmlParserUtils::parseAttributes($element);

        static::assertSame(
            [
                'type' => 'string',
                'value' => ['foo' => 'bar'],
            ],
            $result
        );
    }

    public function testParseChildren(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildren($element);

        static::assertSame(['child1' => 'value1', 'child2' => 'value2'], $result);
    }

    public function testParseChildrenWithTransformer(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildren($element, static fn (\DOMElement $e) => strtoupper($e->nodeValue ?? ''));

        static::assertSame(['child1' => 'VALUE1', 'child2' => 'VALUE2'], $result);
    }

    public function testParseChildrenIgnoresNonDomElements(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMText('test'));

        $result = XmlParserUtils::parseChildren($element);

        static::assertCount(0, $result);
    }

    public function testParseChildrenAsList(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildrenAsList($element);

        static::assertSame(['value1', 'value2'], $result);
    }

    public function testParseChildrenAsListWithTransformer(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildrenAsList($element, static fn (\DOMElement $e) => strtoupper($e->nodeValue ?? ''));

        static::assertSame(['VALUE1', 'VALUE2'], $result);
    }

    public function testParseChildrenAsListIgnoresNonDomElements(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMText('test'));

        $result = XmlParserUtils::parseChildrenAsList($element);

        static::assertCount(0, $result);
    }

    public function testParseChildrenAndTranslate(): void
    {
        $document = new \DOMDocument();
        $element = $document->createElement('test');

        $nameEn = $document->createElement('name', 'EnglishName');
        $nameEn->setAttribute('lang', 'en-GB');

        $labelEn = $document->createElement('label', 'EnglishLabel');
        $labelEn->setAttribute('lang', 'en-GB');

        $nameDe = $document->createElement('name', 'GermanName');
        $nameDe->setAttribute('lang', 'de-DE');

        $labelDe = $document->createElement('label', 'GermanLabel');
        $labelDe->setAttribute('lang', 'de-DE');

        $version = $document->createElement('version', '1.5');

        $element->appendChild($nameEn);
        $element->appendChild($labelEn);
        $element->appendChild($nameDe);
        $element->appendChild($labelDe);
        $element->appendChild($version);

        $result = XmlParserUtils::parseChildrenAndTranslate($element, ['name', 'label']);

        $expectedResult = [
            'name' => [
                'en-GB' => 'EnglishName',
                'de-DE' => 'GermanName',
            ],
            'label' => [
                'en-GB' => 'EnglishLabel',
                'de-DE' => 'GermanLabel',
            ],
            'version' => '1.5',
        ];

        static::assertSame($expectedResult, $result);
    }

    public function testMapTranslatedTag(): void
    {
        $element = $this->createDOMElement();

        /** @var \DOMElement $en */
        $en = $element->appendChild(new \DOMElement('name', 'EnglishName'));
        $en->setAttribute('lang', 'en-GB');

        /** @var \DOMElement $de */
        $de = $element->appendChild(new \DOMElement('name', 'GermanName'));
        $de->setAttribute('lang', 'de-DE');

        $result = XmlParserUtils::mapTranslatedTag($en, []);

        static::assertSame(
            [
                'name' => [
                    'en-GB' => 'EnglishName',
                ],
            ],
            $result
        );

        $result = XmlParserUtils::mapTranslatedTag($de, [
            'name' => [
                'en-GB' => 'EnglishName',
            ],
        ]);

        static::assertSame(
            [
                'name' => [
                    'en-GB' => 'EnglishName',
                    'de-DE' => 'GermanName',
                ],
            ],
            $result
        );
    }

    public function testKebabCaseToCamelCase(): void
    {
        static::assertSame('someValue', XmlParserUtils::kebabCaseToCamelCase('some-value'));
        static::assertSame('someValue', XmlParserUtils::kebabCaseToCamelCase('some_value'));
    }

    /**
     * @param array<string, string>|null $translations
     * @param array<string, string>|null $expected
     */
    #[DataProvider('ensureTranslationForLocaleProvider')]
    public function testEnsureTranslationForLocale(?array $translations, string $locale, ?array $expected): void
    {
        static::assertSame($expected, XmlParserUtils::ensureTranslationForLocale($translations, $locale));
    }

    public static function ensureTranslationForLocaleProvider(): \Generator
    {
        yield 'keeps the translations when the locale is declared' => [
            ['en-GB' => 'English', 'de-DE' => 'German'],
            'de-DE',
            ['en-GB' => 'English', 'de-DE' => 'German'],
        ];

        yield 'does not add a locale that is declared in a different letter case' => [
            ['en-gb' => 'English'],
            'en-GB',
            ['en-gb' => 'English'],
        ];

        yield 'keeps missing translations missing' => [
            null,
            'en-GB',
            null,
        ];

        yield 'keeps empty translations empty' => [
            [],
            'en-GB',
            [],
        ];

        yield 'uses en-GB for another region of English' => [
            ['en-GB' => 'English', 'de-DE' => 'German'],
            'en-US',
            ['en-GB' => 'English', 'de-DE' => 'German', 'en-US' => 'English'],
        ];

        yield 'prefers the main region of the language over earlier declared regions' => [
            ['en-GB' => 'English', 'de-CH' => 'Swiss German', 'de-DE' => 'German'],
            'de-AT',
            ['en-GB' => 'English', 'de-CH' => 'Swiss German', 'de-DE' => 'German', 'de-AT' => 'German'],
        ];

        yield 'uses another region of the language when its main region is not declared' => [
            ['en-GB' => 'English', 'sv-SE' => 'Swedish'],
            'sv-FI',
            ['en-GB' => 'English', 'sv-SE' => 'Swedish', 'sv-FI' => 'Swedish'],
        ];

        yield 'uses the first declared region of the language when several are declared' => [
            ['en-GB' => 'English', 'de-CH' => 'Swiss German', 'de-LU' => 'Luxembourgish German'],
            'de-AT',
            ['en-GB' => 'English', 'de-CH' => 'Swiss German', 'de-LU' => 'Luxembourgish German', 'de-AT' => 'Swiss German'],
        ];

        yield 'uses another English region for en-GB when en-GB is not declared' => [
            ['de-DE' => 'German', 'en-US' => 'American English'],
            'en-GB',
            ['de-DE' => 'German', 'en-US' => 'American English', 'en-GB' => 'American English'],
        ];

        yield 'compares the language case-insensitively' => [
            ['en-GB' => 'English', 'DE-de' => 'German'],
            'de-AT',
            ['en-GB' => 'English', 'DE-de' => 'German', 'de-AT' => 'German'],
        ];

        yield 'resolves a language without region to its main region' => [
            ['en-GB' => 'English', 'de-DE' => 'German'],
            'de',
            ['en-GB' => 'English', 'de-DE' => 'German', 'de' => 'German'],
        ];

        yield 'falls back to en-GB without a translation in the same language' => [
            ['de-DE' => 'German', 'en-GB' => 'English'],
            'fr-FR',
            ['de-DE' => 'German', 'en-GB' => 'English', 'fr-FR' => 'English'],
        ];

        yield 'falls back to the first translation without the same language or en-GB' => [
            ['de-DE' => 'German', 'nl-NL' => 'Dutch'],
            'fr-FR',
            ['de-DE' => 'German', 'nl-NL' => 'Dutch', 'fr-FR' => 'German'],
        ];

        yield 'does not read a language from a value that is no locale' => [
            ['de-DE' => 'German', 'en-GB' => 'English'],
            Defaults::LANGUAGE_SYSTEM,
            ['de-DE' => 'German', 'en-GB' => 'English', Defaults::LANGUAGE_SYSTEM => 'English'],
        ];
    }

    /**
     * @param array<string, string> $attributes
     */
    private function createDOMElement(array $attributes = []): \DOMElement
    {
        $document = new \DOMDocument();
        $element = $document->createElement('test');

        foreach ($attributes as $name => $value) {
            $element->setAttribute($name, $value);
        }

        return $element;
    }
}
