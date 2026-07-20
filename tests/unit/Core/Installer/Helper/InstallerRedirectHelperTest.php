<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Helper\InstallerRedirectHelper;

/**
 * @internal
 */
#[CoversClass(InstallerRedirectHelper::class)]
class InstallerRedirectHelperTest extends TestCase
{
    #[DataProvider('provideSanitizationCases')]
    public function testSanitize(string $queryString, string $expected): void
    {
        $sanitizer = new InstallerRedirectHelper(['QUERY_STRING' => $queryString]);
        $result = $sanitizer->buildQueryString();
        static::assertSame($expected, $result);
    }

    public static function provideSanitizationCases(): \Generator
    {
        yield 'empty string' => [
            'queryString' => '',
            'expected' => '',
        ];

        yield 'valid language: en-GB' => [
            'queryString' => 'language=en-GB',
            'expected' => '?language=en-GB',
        ];

        yield 'valid language: de-DE' => [
            'queryString' => 'language=de-DE',
            'expected' => '?language=de-DE',
        ];

        yield 'valid language: two-letter code only' => [
            'queryString' => 'language=en',
            'expected' => '?language=en',
        ];

        yield 'invalid language: with script (Chinese Traditional)' => [
            'queryString' => 'language=zh-Hant-TW',
            'expected' => '',
        ];

        yield 'invalid language: with numeric region code' => [
            'queryString' => 'language=es-419',
            'expected' => '',
        ];

        yield 'invalid language: three-letter code' => [
            'queryString' => 'language=eng',
            'expected' => '',
        ];

        yield 'invalid language: too short' => [
            'queryString' => 'language=e',
            'expected' => '',
        ];

        yield 'invalid language: numbers' => [
            'queryString' => 'language=12',
            'expected' => '',
        ];

        yield 'invalid language: underscore' => [
            'queryString' => 'language=en_GB',
            'expected' => '',
        ];

        yield 'invalid language: lowercase region code' => [
            'queryString' => 'language=en-gb',
            'expected' => '',
        ];

        yield 'valid ext_steps: value is 1' => [
            'queryString' => 'ext_steps=1',
            'expected' => '?ext_steps=1',
        ];

        yield 'invalid ext_steps: value is 0' => [
            'queryString' => 'ext_steps=0',
            'expected' => '',
        ];

        yield 'invalid ext_steps: value is 2' => [
            'queryString' => 'ext_steps=2',
            'expected' => '',
        ];

        yield 'invalid ext_steps: value is true' => [
            'queryString' => 'ext_steps=true',
            'expected' => '',
        ];

        yield 'both valid parameters are sorted' => [
            'queryString' => 'language=de-DE&ext_steps=1',
            'expected' => '?ext_steps=1&language=de-DE',
        ];

        yield 'both valid parameters (reverse order)' => [
            'queryString' => 'ext_steps=1&language=en-GB',
            'expected' => '?ext_steps=1&language=en-GB',
        ];

        yield 'valid language with invalid ext_steps' => [
            'queryString' => 'language=en-GB&ext_steps=2',
            'expected' => '?language=en-GB',
        ];

        yield 'invalid language with valid ext_steps' => [
            'queryString' => 'language=invalid&ext_steps=1',
            'expected' => '?ext_steps=1',
        ];

        yield 'disallowed parameter: redirect' => [
            'queryString' => 'redirect=/admin',
            'expected' => '',
        ];

        yield 'valid language with disallowed parameters' => [
            'queryString' => 'language=en-GB&redirect=/admin&foo=bar',
            'expected' => '?language=en-GB',
        ];

        yield 'all parameters: valid, invalid, and disallowed' => [
            'queryString' => 'language=en-GB&ext_steps=1&redirect=/admin&invalid=value',
            'expected' => '?ext_steps=1&language=en-GB',
        ];

        yield 'language as array takes first value' => [
            'queryString' => 'language[]=en-GB&language[]=de-DE',
            'expected' => '?language=en-GB',
        ];

        yield 'ext_steps as array takes first value' => [
            'queryString' => 'ext_steps[]=1&ext_steps[]=2',
            'expected' => '?ext_steps=1',
        ];

        yield 'URL encoded language parameter' => [
            'queryString' => 'language=en%2DGB',
            'expected' => '?language=en-GB',
        ];

        yield 'XSS attempt in language' => [
            'queryString' => 'language=<script>alert(1)</script>',
            'expected' => '',
        ];

        yield 'SQL injection attempt in ext_steps' => [
            'queryString' => 'ext_steps=1\' OR \'1\'=\'1',
            'expected' => '',
        ];

        yield 'null bytes are truncated by parse_str, leaving valid language' => [
            'queryString' => 'language=en' . "\0" . 'GB',
            'expected' => '?language=en',
        ];
    }

    public function testConstructorWithoutQueryString(): void
    {
        $sanitizer = new InstallerRedirectHelper(['REQUEST_URI' => '/installer']);
        $result = $sanitizer->buildQueryString();

        static::assertSame('', $result, 'Should return empty string when no QUERY_STRING in $_SERVER');
    }
}
