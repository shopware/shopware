<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Xml\XmlFormatter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(XmlFormatter::class)]
class XmlFormatterTest extends TestCase
{
    public function testPrettyPrintsCompactInput(): void
    {
        $input = '<root><child attr="v">text</child><empty/></root>';
        $output = (new XmlFormatter())->format($input);

        static::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $output);
        static::assertStringContainsString("<root>\n", $output);
        static::assertStringContainsString('<child attr="v">text</child>', $output);
        static::assertStringContainsString('<empty/>', $output);
        static::assertStringEndsWith("</root>\n", $output);
    }

    public function testPreservesEmptyElementsAsSelfClosing(): void
    {
        $output = (new XmlFormatter())->format('<root><empty></empty></root>');

        static::assertStringContainsString('<empty/>', $output);
    }

    public function testPreservesAttributeQuoting(): void
    {
        $output = (new XmlFormatter())->format('<root attr="value with spaces"/>');

        static::assertStringContainsString('attr="value with spaces"', $output);
    }

    public function testPreservesNamespaceDeclarations(): void
    {
        $input = '<rsm:Invoice xmlns:rsm="urn:test"><rsm:ID>1</rsm:ID></rsm:Invoice>';
        $output = (new XmlFormatter())->format($input);

        static::assertStringContainsString('xmlns:rsm="urn:test"', $output);
        static::assertStringContainsString('<rsm:ID>1</rsm:ID>', $output);
    }

    public function testPreservesUnicodeContent(): void
    {
        $input = '<root>Schöppingen</root>';
        $output = (new XmlFormatter())->format($input);

        static::assertStringContainsString('Schöppingen', $output);
    }

    public function testThrowsOnMalformedXml(): void
    {
        static::expectException(DocumentV2Exception::class);
        static::expectExceptionMessageMatches('/Generated XML is malformed/');

        (new XmlFormatter())->format('<root><unclosed></root>');
    }

    public function testThrowsOnEmptyInput(): void
    {
        static::expectException(DocumentV2Exception::class);

        (new XmlFormatter())->format('');
    }

    public function testThrowsOnNonXmlInput(): void
    {
        static::expectException(DocumentV2Exception::class);

        (new XmlFormatter())->format('this is not xml');
    }

    public function testDoesNotLeakLibxmlState(): void
    {
        $previousUseInternalErrors = libxml_use_internal_errors(false);
        libxml_clear_errors();

        try {
            (new XmlFormatter())->format('<root/>');

            static::assertFalse(libxml_use_internal_errors(false));
            static::assertSame([], libxml_get_errors());
        } finally {
            libxml_use_internal_errors($previousUseInternalErrors);
        }
    }

    public function testMalformedThrowAlsoDoesNotLeakLibxmlState(): void
    {
        $previousUseInternalErrors = libxml_use_internal_errors(false);
        libxml_clear_errors();

        try {
            (new XmlFormatter())->format('<bad');
        } catch (DocumentV2Exception) {
            // expected
        }

        static::assertFalse(libxml_use_internal_errors(false));
        static::assertSame([], libxml_get_errors());

        libxml_use_internal_errors($previousUseInternalErrors);
    }
}
