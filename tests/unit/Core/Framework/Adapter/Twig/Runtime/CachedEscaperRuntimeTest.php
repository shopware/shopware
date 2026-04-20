<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Runtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Error\RuntimeError;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[CoversClass(CachedEscaperRuntime::class)]
class CachedEscaperRuntimeTest extends TestCase
{
    private CachedEscaperRuntime $escaper;

    /**
     * All character encodings supported by htmlspecialchars().
     *
     * @var array<string, string>
     */
    private static array $htmlSpecialChars = [
        '\'' => '&#039;',
        '"' => '&quot;',
        '<' => '&lt;',
        '>' => '&gt;',
        '&' => '&amp;',
    ];

    /**
     * @var array<int|string, string>
     */
    private static array $htmlAttrSpecialChars = [
        '\'' => '&#x27;',
        /* Characters beyond ASCII value 255 to unicode escape */
        'Ā' => '&#x0100;',
        '😀' => '&#x1F600;',
        /* Immune chars excluded */
        ',' => ',',
        '.' => '.',
        '-' => '-',
        '_' => '_',
        /* Basic alnums excluded */
        'a' => 'a',
        'A' => 'A',
        'z' => 'z',
        'Z' => 'Z',
        0 => '0',
        9 => '9',
        /* Basic control characters and null */
        "\r" => '&#x0D;',
        "\n" => '&#x0A;',
        "\t" => '&#x09;',
        "\0" => '&#xFFFD;', // should use Unicode replacement char
        /* Encode chars as named entities where possible */
        '<' => '&lt;',
        '>' => '&gt;',
        '&' => '&amp;',
        '"' => '&quot;',
        /* Encode spaces for quote-less attribute protection */
        ' ' => '&#x20;',
    ];

    /**
     * @var array<int|string, string>
     */
    private static array $jsSpecialChars = [
        /* HTML special chars - escape without exception to hex */
        '<' => '\\u003C',
        '>' => '\\u003E',
        '\'' => '\\u0027',
        '"' => '\\u0022',
        '&' => '\\u0026',
        '/' => '\\/',
        /* Characters beyond ASCII value 255 to unicode escape */
        'Ā' => '\\u0100',
        '😀' => '\\uD83D\\uDE00',
        /* Immune chars excluded */
        ',' => ',',
        '.' => '.',
        '_' => '_',
        /* Basic alnums excluded */
        'a' => 'a',
        'A' => 'A',
        'z' => 'z',
        'Z' => 'Z',
        0 => '0',
        9 => '9',
        /* Basic control characters and null */
        "\r" => '\r',
        "\n" => '\n',
        "\x08" => '\b',
        "\t" => '\t',
        "\x0C" => '\f',
        "\0" => '\\u0000',
        /* Encode spaces for quote-less attribute protection */
        ' ' => '\\u0020',
    ];

    /**
     * @var array<int|string, string>
     */
    private static array $urlSpecialChars = [
        /* HTML special chars - escape without exception to percent encoding */
        '<' => '%3C',
        '>' => '%3E',
        '\'' => '%27',
        '"' => '%22',
        '&' => '%26',
        /* Characters beyond ASCII value 255 to hex sequence */
        'Ā' => '%C4%80',
        /* Punctuation and unreserved check */
        ',' => '%2C',
        '.' => '.',
        '_' => '_',
        '-' => '-',
        ':' => '%3A',
        ';' => '%3B',
        '!' => '%21',
        /* Basic alnums excluded */
        'a' => 'a',
        'A' => 'A',
        'z' => 'z',
        'Z' => 'Z',
        0 => '0',
        9 => '9',
        /* Basic control characters and null */
        "\r" => '%0D',
        "\n" => '%0A',
        "\t" => '%09',
        "\0" => '%00',
        /* PHP quirks from the past */
        ' ' => '%20',
        '~' => '~',
        '+' => '%2B',
    ];

    /**
     * @var array<int|string, string>
     */
    private static array $cssSpecialChars = [
        /* HTML special chars - escape without exception to hex */
        '<' => '\\3C ',
        '>' => '\\3E ',
        '\'' => '\\27 ',
        '"' => '\\22 ',
        '&' => '\\26 ',
        /* Characters beyond ASCII value 255 to unicode escape */
        'Ā' => '\\100 ',
        /* Immune chars excluded */
        ',' => '\\2C ',
        '.' => '\\2E ',
        '_' => '\\5F ',
        /* Basic alnums excluded */
        'a' => 'a',
        'A' => 'A',
        'z' => 'z',
        'Z' => 'Z',
        0 => '0',
        9 => '9',
        /* Basic control characters and null */
        "\r" => '\\D ',
        "\n" => '\\A ',
        "\t" => '\\9 ',
        "\0" => '\\0 ',
        /* Encode spaces for quote-less attribute protection */
        ' ' => '\\20 ',
    ];

    protected function setUp(): void
    {
        $this->escaper = new CachedEscaperRuntime(new EscaperRuntime());
    }

    #[TestDox('Ensures that the decoration stays in sync with upstream')]
    public function testAllPublicMethodsAreMimicked(): void
    {
        $originalMethods = (new \ReflectionClass(EscaperRuntime::class))->getMethods(\ReflectionMethod::IS_PUBLIC);
        $originalMethodNames = [];
        foreach ($originalMethods as $method) {
            $originalMethodNames[] = $method->getName();
        }

        $cachedMethods = (new \ReflectionClass(CachedEscaperRuntime::class))->getMethods(\ReflectionMethod::IS_PUBLIC);
        $cachedMethodNames = [];
        foreach ($cachedMethods as $method) {
            $cachedMethodNames[] = $method->getName();
        }

        // Will fail, if methods are added upstream
        static::assertSame([], array_diff($originalMethodNames, $cachedMethodNames));
    }

    public function testHtmlEscapingConvertsSpecialChars(): void
    {
        foreach (self::$htmlSpecialChars as $key => $value) {
            static::assertSame($value, $this->escaper->escape($key), 'Failed to escape: ' . $key);
        }
    }

    public function testHtmlAttributeEscapingConvertsSpecialChars(): void
    {
        foreach (self::$htmlAttrSpecialChars as $key => $value) {
            static::assertSame($value, $this->escaper->escape($key, 'html_attr'), 'Failed to escape: ' . $key);
        }
    }

    public function testJavascriptEscapingConvertsSpecialChars(): void
    {
        foreach (self::$jsSpecialChars as $key => $value) {
            static::assertSame($value, $this->escaper->escape($key, 'js'), 'Failed to escape: ' . $key);
        }
    }

    public function testJavascriptEscapingConvertsSpecialCharsWithInternalEncoding(): void
    {
        $previousInternalEncoding = mb_internal_encoding();

        try {
            mb_internal_encoding('ISO-8859-1');
            foreach (self::$jsSpecialChars as $key => $value) {
                static::assertSame($value, $this->escaper->escape($key, 'js'), 'Failed to escape: ' . $key);
            }
        } finally {
            if ($previousInternalEncoding !== false) {
                mb_internal_encoding($previousInternalEncoding);
            }
        }
    }

    public function testJavascriptEscapingReturnsStringIfZeroLength(): void
    {
        static::assertSame('', $this->escaper->escape('', 'js'));
    }

    public function testJavascriptEscapingReturnsStringIfContainsOnlyDigits(): void
    {
        static::assertSame('123', $this->escaper->escape('123', 'js'));
    }

    public function testCssEscapingConvertsSpecialChars(): void
    {
        foreach (self::$cssSpecialChars as $key => $value) {
            static::assertSame($value, $this->escaper->escape($key, 'css'), 'Failed to escape: ' . $key);
        }
    }

    public function testCssEscapingReturnsStringIfZeroLength(): void
    {
        static::assertSame('', $this->escaper->escape('', 'css'));
    }

    public function testCssEscapingReturnsStringIfContainsOnlyDigits(): void
    {
        static::assertSame('123', $this->escaper->escape('123', 'css'));
    }

    public function testUrlEscapingConvertsSpecialChars(): void
    {
        foreach (self::$urlSpecialChars as $key => $value) {
            static::assertSame($value, $this->escaper->escape($key, 'url'), 'Failed to escape: ' . $key);
        }
    }

    /**
     * Range tests to confirm escaped range of characters is within OWASP recommendation.
     *
     * Only testing the first few 2 ranges on this protected function as that's all these
     * other range tests require.
     */
    public function testUnicodeCodepointConversionToUtf8(): void
    {
        $expected = ' ~ޙ';
        $codepoints = [0x20, 0x7E, 0x799];
        $result = '';
        foreach ($codepoints as $value) {
            $result .= $this->codepointToUtf8($value);
        }
        static::assertSame($expected, $result);
    }

    public function testJavascriptEscapingEscapesOwaspRecommendedRanges(): void
    {
        $immune = [',', '.', '_']; // Exceptions to escaping ranges
        for ($chr = 0; $chr < 0xFF; ++$chr) {
            if (($chr >= 0x30 && $chr <= 0x39)
                || ($chr >= 0x41 && $chr <= 0x5A)
                || ($chr >= 0x61 && $chr <= 0x7A)) {
                $literal = $this->codepointToUtf8($chr);
                static::assertSame($literal, $this->escaper->escape($literal, 'js'));
            } else {
                $literal = $this->codepointToUtf8($chr);
                if (\in_array($literal, $immune, true)) {
                    static::assertSame($literal, $this->escaper->escape($literal, 'js'));
                } else {
                    static::assertNotSame(
                        $literal,
                        $this->escaper->escape($literal, 'js'),
                        "$literal should be escaped!"
                    );
                }
            }
        }
    }

    public function testHtmlAttributeEscapingEscapesOwaspRecommendedRanges(): void
    {
        $immune = [',', '.', '-', '_']; // Exceptions to escaping ranges
        for ($chr = 0; $chr < 0xFF; ++$chr) {
            if (($chr >= 0x30 && $chr <= 0x39)
                || ($chr >= 0x41 && $chr <= 0x5A)
                || ($chr >= 0x61 && $chr <= 0x7A)) {
                $literal = $this->codepointToUtf8($chr);
                static::assertSame($literal, $this->escaper->escape($literal, 'html_attr'));
            } else {
                $literal = $this->codepointToUtf8($chr);
                if (\in_array($literal, $immune, true)) {
                    static::assertSame($literal, $this->escaper->escape($literal, 'html_attr'));
                } else {
                    static::assertNotSame(
                        $literal,
                        $this->escaper->escape($literal, 'html_attr'),
                        "$literal should be escaped!"
                    );
                }
            }
        }
    }

    public function testCssEscapingEscapesOwaspRecommendedRanges(): void
    {
        // CSS has no exceptions to escaping ranges
        for ($chr = 0; $chr < 0xFF; ++$chr) {
            if (($chr >= 0x30 && $chr <= 0x39)
                || ($chr >= 0x41 && $chr <= 0x5A)
                || ($chr >= 0x61 && $chr <= 0x7A)) {
                $literal = $this->codepointToUtf8($chr);
                static::assertSame($literal, $this->escaper->escape($literal, 'css'));
            } else {
                $literal = $this->codepointToUtf8($chr);
                static::assertNotSame(
                    $literal,
                    $this->escaper->escape($literal, 'css'),
                    "$literal should be escaped!"
                );
            }
        }
    }

    #[DataProvider('provideCustomEscaperCases')]
    public function testCustomEscaper(string $expected, int|string|null $string, string $strategy): void
    {
        $escapeRuntime = new EscaperRuntime();
        $escapeRuntime->setEscaper('foo', foo_escaper_for_test(...));
        $cachedEscaper = new CachedEscaperRuntime($escapeRuntime);

        static::assertSame($expected, $cachedEscaper->escape($string, $strategy));
    }

    /**
     * @return list<array{string, int|string|null, string}>
     */
    public static function provideCustomEscaperCases(): array
    {
        return [
            ['FOO', 'foo', 'foo'],
            ['FOO', 'fOo', 'foo'],
            ['', '', 'foo'],
            ['', null, 'foo'],
            ['42', 42, 'foo'],
        ];
    }

    public function testUnknownCustomEscaper(): void
    {
        $this->expectExceptionObject(new RuntimeError('Invalid escaping strategy "bar" (valid ones: "html", "js", "url", "css", "html_attr", "html_attr_relaxed")'));
        $this->escaper->escape('foo', 'bar');
    }

    /**
     * @param array<class-string<Extension_TestClass>, list<string>> $safeClasses
     */
    #[DataProvider('provideObjectsForEscaping')]
    public function testObjectEscaping(string $escapedHtml, string $escapedJs, array $safeClasses): void
    {
        $obj = new Extension_TestClass();
        $escapeRuntime = new EscaperRuntime();
        $escapeRuntime->setSafeClasses($safeClasses);

        $cachedEscaper = new CachedEscaperRuntime($escapeRuntime);

        static::assertSame($escapedHtml, $cachedEscaper->escape($obj, 'html', null, true));
        static::assertSame($escapedJs, $cachedEscaper->escape($obj, 'js', null, true));
    }

    /**
     * @return list<array{string, string, array<class-string<Extension_TestClass>, list<string>>}>
     */
    public static function provideObjectsForEscaping(): array
    {
        return [
            ['&lt;br /&gt;', '<br />', ['\\' . Extension_TestClass::class => ['js']]],
            ['<br />', '\u003Cbr\u0020\/\u003E', ['\\' . Extension_TestClass::class => ['html']]],
            ['&lt;br /&gt;', '<br />', ['\\' . Extension_TestClass::class => ['js']]],
            ['<br />', '<br />', ['\\' . Extension_TestClass::class => ['all']]],
        ];
    }

    /**
     * @return \Generator<string, array{input: int|string|null, expected: string}>
     */
    public static function EscapeDataProvider(): \Generator
    {
        yield 'null input' => [
            'input' => null,
            'expected' => '',
        ];

        yield 'integer input' => [
            'input' => 123,
            'expected' => '123',
        ];

        yield 'string input' => [
            'input' => 'test',
            'expected' => 'test',
        ];

        yield 'escaped string input' => [
            'input' => '<script>alert("test")</script>',
            'expected' => '&lt;script&gt;alert(&quot;test&quot;)&lt;/script&gt;',
        ];
    }

    #[DataProvider('EscapeDataProvider')]
    public function testEscapeWithVariousInputs(int|string|null $input, string $expected): void
    {
        $result = $this->escaper->escape($input, 'html', 'UTF-8');

        static::assertSame($expected, $result);
    }

    public function testEscapeWithCache(): void
    {
        $this->escaper::resetEscapeCache();

        // First call to cache the result
        $string = 'cached_string';
        $result1 = $this->escaper->escape($string, 'html', 'UTF-8');

        // Make sure result is cached
        $cache = (new \ReflectionProperty($this->escaper, 'escapeCache'))->getValue();
        static::assertCount(1, $cache);
        static::assertArrayHasKey($string, $cache);

        // Second call to get the cached result
        $result2 = $this->escaper->escape($string, 'html', 'UTF-8');

        // Assert that the results are the same, indicating the cache was used
        static::assertSame($result1, $result2);
    }

    public function testEscapeUuidWithCache(): void
    {
        $this->escaper::resetEscapeCache();

        // First call to cache the result
        $string = Uuid::randomHex();
        $result1 = $this->escaper->escape($string, 'html', 'UTF-8');

        // Make sure result is cached
        $cache = (new \ReflectionProperty($this->escaper, 'escapeCache'))->getValue();
        static::assertCount(1, $cache);
        static::assertArrayHasKey($string, $cache);

        // Second call to get the cached result
        $result2 = $this->escaper->escape($string, 'html', 'UTF-8');

        // Assert that the results are the same, indicating the cache was used
        static::assertSame($result1, $result2);
    }

    public function testEscapeDoesNotCacheBooleanInput(): void
    {
        $this->escaper::resetEscapeCache();

        $cacheProperty = new \ReflectionProperty($this->escaper, 'escapeCache');
        $cacheBefore = $cacheProperty->getValue();
        static::assertCount(0, $cacheBefore);

        $result1 = $this->escaper->escape(true, 'html', 'UTF-8');
        $cacheAfterFirstCall = $cacheProperty->getValue();
        static::assertCount(0, $cacheAfterFirstCall);

        $result2 = $this->escaper->escape(true, 'html', 'UTF-8');
        $cacheAfterSecondCall = $cacheProperty->getValue();
        static::assertCount(0, $cacheAfterSecondCall);

        static::assertSame($result1, $result2);
    }

    /**
     * Convert a Unicode Codepoint to a literal UTF-8 character.
     *
     * @param int $codepoint Unicode codepoint in hex notation
     *
     * @return string UTF-8 literal string
     */
    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0x80) {
            return \chr($codepoint);
        }
        if ($codepoint < 0x800) {
            return \chr($codepoint >> 6 & 0x3F | 0xC0)
                . \chr($codepoint & 0x3F | 0x80);
        }
        if ($codepoint < 0x10000) {
            return \chr($codepoint >> 12 & 0x0F | 0xE0)
                . \chr($codepoint >> 6 & 0x3F | 0x80)
                . \chr($codepoint & 0x3F | 0x80);
        }
        if ($codepoint < 0x110000) {
            return \chr($codepoint >> 18 & 0x07 | 0xF0)
                . \chr($codepoint >> 12 & 0x3F | 0x80)
                . \chr($codepoint >> 6 & 0x3F | 0x80)
                . \chr($codepoint & 0x3F | 0x80);
        }

        throw new \InvalidArgumentException('Codepoint requested outside of Unicode range.');
    }
}

function foo_escaper_for_test(string $string): string
{
    return strtoupper($string);
}

/**
 * @internal
 */
class Extension_TestClass implements \Stringable
{
    public function __toString(): string
    {
        return '<br />';
    }
}
