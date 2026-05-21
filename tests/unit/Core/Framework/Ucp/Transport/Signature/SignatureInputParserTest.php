<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Transport\Signature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Transport\Signature\SignatureInputParser;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @internal
 */
#[CoversClass(SignatureInputParser::class)]
class SignatureInputParserTest extends TestCase
{
    public function testParsesSingleLabel(): void
    {
        $parser = new SignatureInputParser();
        $header = 'sig1=("@method" "@target-uri" "content-digest");created=1730000000;keyid="ucp_2026_abc"';

        $result = $parser->parse($header);

        static::assertArrayHasKey('sig1', $result);
        $entry = $result['sig1'];
        static::assertSame(['@method', '@target-uri', 'content-digest'], $entry['components']->components);
        static::assertSame('1730000000', $entry['components']->getParameter('created'));
        static::assertSame('ucp_2026_abc', $entry['components']->getKeyId());
    }

    public function testParsesNoParameters(): void
    {
        $parser = new SignatureInputParser();
        $result = $parser->parse('sig1=("@method")');

        static::assertArrayHasKey('sig1', $result);
        static::assertSame(['@method'], $result['sig1']['components']->components);
    }

    public function testRejectsMalformedInput(): void
    {
        $parser = new SignatureInputParser();
        $this->expectException(UcpException::class);
        $parser->parse('garbage');
    }

    public function testValueIsReconstructible(): void
    {
        $parser = new SignatureInputParser();
        $result = $parser->parse('sig1=("@method" "@target-uri");keyid="abc"');
        $value = $result['sig1']['value'];
        static::assertStringContainsString('"@method"', $value);
        static::assertStringContainsString('"@target-uri"', $value);
        static::assertStringContainsString('keyid="abc"', $value);
    }
}
