<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\Framework\Util\XmlParser;
use Symfony\Component\Config\Util\Exception\InvalidXmlException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(XmlParser::class)]
class XmlParserTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures/xml-parser';

    protected function setUp(): void
    {
        ExternalResourceProbe::register();
    }

    protected function tearDown(): void
    {
        ExternalResourceProbe::unregister();
    }

    public function testParseReturnsTheDocument(): void
    {
        $dom = XmlParser::parse('<?xml version="1.0"?><config><title>Example</title></config>');

        static::assertSame('Example', $dom->getElementsByTagName('title')->item(0)?->textContent);
    }

    public function testParseValidatesTheDocument(): void
    {
        $this->expectExceptionObject(new InvalidXmlException('The XML is not valid.'));

        XmlParser::parse('<?xml version="1.0"?><config/>', static fn (): bool => false);
    }

    #[DataProvider('documentsWithDocumentType')]
    public function testParseRejectsDocumentTypesWithoutLoadingExternalResources(string $xml): void
    {
        try {
            XmlParser::parse($xml);
            static::fail('A document with a document type must be rejected.');
        } catch (UtilException $e) {
            static::assertEquals(UtilException::xmlParsingFailed('Document types are not allowed.'), $e);
        }

        static::assertSame([], ExternalResourceProbe::$accessed);
    }

    public static function documentsWithDocumentType(): \Generator
    {
        yield 'external DTD subset is not fetched' => [
            '<?xml version="1.0"?><!DOCTYPE config SYSTEM "xxe-probe://subset"><config/>',
        ];

        yield 'external parameter entity is not fetched' => [
            '<?xml version="1.0"?><!DOCTYPE config [<!ENTITY % remote SYSTEM "xxe-probe://parameter-entity"> %remote;]><config/>',
        ];

        yield 'external general entity is not fetched' => [
            '<?xml version="1.0"?><!DOCTYPE config [<!ENTITY remote SYSTEM "xxe-probe://general-entity">]><config>&remote;</config>',
        ];

        yield 'internal entity declarations are rejected as well' => [
            '<?xml version="1.0"?><!DOCTYPE config [<!ENTITY name "value">]><config>&name;</config>',
        ];
    }

    public function testParseReportsMalformedXml(): void
    {
        $this->expectExceptionObject(UtilException::xmlParsingFailed(
            "[ERROR 76] Opening and ending tag mismatch: title line 1 and config (in n/a - line 1, column 46)\n"
            . '[ERROR 77] Premature end of data in tag config line 1 (in n/a - line 1, column 46)'
        ));

        XmlParser::parse('<?xml version="1.0"?><config><title></config>');
    }

    public function testParseFetchesNothingWhenTheBodyAfterADocumentTypeIsMalformed(): void
    {
        try {
            XmlParser::parse('<?xml version="1.0"?><!DOCTYPE config SYSTEM "xxe-probe://subset"><config>');
            static::fail('Malformed XML must be rejected.');
        } catch (UtilException) {
        }

        static::assertSame([], ExternalResourceProbe::$accessed);
    }

    public function testParseRestoresTheGlobalLibxmlState(): void
    {
        $loader = static fn (?string $publicId, string $systemId, array $context): null => null;
        libxml_set_external_entity_loader($loader);
        $internalErrors = libxml_use_internal_errors(false);

        try {
            XmlParser::parse('<?xml version="1.0"?><!DOCTYPE config SYSTEM "xxe-probe://subset"><config/>');
        } catch (UtilException) {
        }

        static::assertSame($loader, libxml_get_external_entity_loader());
        static::assertFalse(libxml_use_internal_errors($internalErrors));
        libxml_set_external_entity_loader(null);
    }

    public function testLoadFileReturnsTheDocument(): void
    {
        $dom = XmlParser::loadFile(self::FIXTURES . '/valid.xml');

        static::assertSame('Example', $dom->getElementsByTagName('title')->item(0)?->textContent);
    }

    public function testLoadFileRejectsDocumentTypesWithoutLoadingExternalResources(): void
    {
        try {
            XmlParser::loadFile(self::FIXTURES . '/document-type.xml');
            static::fail('A document with a document type must be rejected.');
        } catch (UtilException $e) {
            static::assertEquals(UtilException::xmlParsingFailed('Document types are not allowed.'), $e);
        }

        static::assertSame([], ExternalResourceProbe::$accessed);
    }

    public function testLoadFileNamesTheFileOfAnInvalidDocument(): void
    {
        $file = self::FIXTURES . '/valid.xml';

        $this->expectExceptionObject(UtilException::xmlParsingFailed(\sprintf('The XML file "%s" is not valid.', $file)));

        XmlParser::loadFile($file, static fn (): bool => false);
    }

    public function testLoadFileRejectsAMissingFile(): void
    {
        $file = self::FIXTURES . '/missing.xml';

        $this->expectExceptionObject(UtilException::xmlParsingFailed(\sprintf('Resource "%s" is not a file.', $file)));

        XmlParser::loadFile($file);
    }
}

/**
 * Records every access libxml makes through the `xxe-probe://` stream wrapper, without serving any content.
 *
 * @internal
 */
class ExternalResourceProbe
{
    private const PROTOCOL = 'xxe-probe';

    /**
     * @var list<string>
     */
    public static array $accessed = [];

    /**
     * @var resource|null
     */
    public $context;

    public static function register(): void
    {
        self::$accessed = [];
        stream_wrapper_register(self::PROTOCOL, self::class);
    }

    public static function unregister(): void
    {
        stream_wrapper_unregister(self::PROTOCOL);
    }

    /**
     * @return array<string, int>
     */
    public function url_stat(string $path, int $flags): array
    {
        self::$accessed[] = $path;

        return ['size' => 0, 'mode' => 0100644];
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$accessed[] = $path;

        return false;
    }
}
