<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;

/**
 * @internal
 */
#[CoversClass(XmlParserUtils::class)]
class XmlParserUtilsTest extends TestCase
{
    public function testParseAttributes(): void
    {
        $element = $this->createDOMElement(['attr1' => 'value1', 'attr_2' => 'value2']);

        $result = XmlParserUtils::parseAttributes($element);

        static::assertEquals(['attr1' => 'value1', 'attr2' => 'value2'], $result);
    }

    public function testParseChildren(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildren($element);

        static::assertEquals(['child1' => 'value1', 'child2' => 'value2'], $result);
    }

    public function testParseChildrenWithTransformer(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildren($element, fn (\DOMElement $e) => strtoupper($e->nodeValue ?? ''));

        static::assertEquals(['child1' => 'VALUE1', 'child2' => 'VALUE2'], $result);
    }

    public function testParseChildrenIgnoresNonDomElements(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMText('test'));

        $result = XmlParserUtils::parseChildren($element);

        static::assertEmpty($result);
    }

    public function testParseChildrenAsList(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildrenAsList($element);

        static::assertEquals(['value1', 'value2'], $result);
    }

    public function testParseChildrenAsListWithTransformer(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildrenAsList($element, fn (\DOMElement $e) => strtoupper($e->nodeValue ?? ''));

        static::assertEquals(['VALUE1', 'VALUE2'], $result);
    }

    public function testParseChildrenAsListIgnoresNonDomElements(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMText('test'));

        $result = XmlParserUtils::parseChildrenAsList($element);

        static::assertEmpty($result);
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

        static::assertEquals($expectedResult, $result);
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

        static::assertEquals(
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

        static::assertEquals(
            [
                'name' => [
                    'en-GB' => 'EnglishName',
                    'de-DE' => 'GermanName',
                ],
            ],
            $result
        );
    }

    public function testParseChildrenAndTranslateWithExistingValues(): void
    {
        $element = $this->createDOMElement();

        /** @var \DOMElement $nameEn */
        $nameEn = $element->appendChild(new \DOMElement('name', 'EnglishName'));
        $nameEn->setAttribute('lang', 'en-GB');

        /** @var \DOMElement $labelEn */
        $labelEn = $element->appendChild(new \DOMElement('label', 'EnglishLabel'));
        $labelEn->setAttribute('lang', 'en-GB');

        /** @var \DOMElement $nameDe */
        $nameDe = $element->appendChild(new \DOMElement('name', 'GermanName'));
        $nameDe->setAttribute('lang', 'de-DE');

        /** @var \DOMElement $labelDe */
        $labelDe = $element->appendChild(new \DOMElement('label', 'GermanLabel'));
        $labelDe->setAttribute('lang', 'de-DE');

        $element->appendChild(new \DOMElement('version', '1.5'));

        $result = XmlParserUtils::parseChildrenAndTranslate($element, ['name', 'label'], ['location' => ['en-GB' => 'one', 'de-DE' => 'two']]);

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
            'location' => [
                'en-GB' => 'one',
                'de-DE' => 'two',
            ],
        ];

        static::assertEquals($expectedResult, $result);
    }

    public function testKebabCaseToCamelCase(): void
    {
        static::assertEquals('someValue', XmlParserUtils::kebabCaseToCamelCase('some-value'));
        static::assertEquals('someValue', XmlParserUtils::kebabCaseToCamelCase('some_value'));
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
