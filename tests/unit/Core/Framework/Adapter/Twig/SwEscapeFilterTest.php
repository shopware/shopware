<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\LoaderInterface;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[CoversClass('Shopware\Core\Framework\Adapter\Twig\SwTwigFunction')]
class SwEscapeFilterTest extends TestCase
{
    /**
     * All character encodings supported by htmlspecialchars().
     *
     * @var array<int|string, string>
     */
    protected array $htmlSpecialChars = [
        '\'' => '&#039;',
        '"' => '&quot;',
        '<' => '&lt;',
        '>' => '&gt;',
        '&' => '&amp;',
    ];

    /**
     * @var array<int|string, string>
     */
    protected array $htmlAttrSpecialChars = [
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
        '0' => '0',
        '9' => '9',
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
        /* Encode spaces for quoteless attribute protection */
        ' ' => '&#x20;',
    ];

    /**
     * @var array<int|string, string>
     */
    protected array $jsSpecialChars = [
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
        '0' => '0',
        '9' => '9',
        /* Basic control characters and null */
        "\r" => '\r',
        "\n" => '\n',
        "\x08" => '\b',
        "\t" => '\t',
        "\x0C" => '\f',
        "\0" => '\\u0000',
        /* Encode spaces for quoteless attribute protection */
        ' ' => '\\u0020',
    ];

    /**
     * @var array<int|string, string>
     */
    protected array $urlSpecialChars = [
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
        '0' => '0',
        '9' => '9',
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
    protected array $cssSpecialChars = [
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
        '0' => '0',
        '9' => '9',
        /* Basic control characters and null */
        "\r" => '\\D ',
        "\n" => '\\A ',
        "\t" => '\\9 ',
        "\0" => '\\0 ',
        /* Encode spaces for quoteless attribute protection */
        ' ' => '\\20 ',
    ];

    public function testHtmlEscapingConvertsSpecialChars(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        foreach ($this->htmlSpecialChars as $key => $value) {
            static::assertEquals($value, SwTwigFunction::escapeFilter($twig, $key), 'Failed to escape: ' . $key);
        }
    }

    public function testHtmlAttributeEscapingConvertsSpecialChars(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        foreach ($this->htmlAttrSpecialChars as $key => $value) {
            static::assertEquals($value, SwTwigFunction::escapeFilter($twig, $key, 'html_attr'), 'Failed to escape: ' . $key);
        }
    }

    public function testJavascriptEscapingConvertsSpecialChars(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        foreach ($this->jsSpecialChars as $key => $value) {
            static::assertEquals($value, SwTwigFunction::escapeFilter($twig, $key, 'js'), 'Failed to escape: ' . $key);
        }
    }

    public function testJavascriptEscapingConvertsSpecialCharsWithInternalEncoding(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        $previousInternalEncoding = mb_internal_encoding();

        try {
            mb_internal_encoding('ISO-8859-1');
            foreach ($this->jsSpecialChars as $key => $value) {
                static::assertEquals($value, SwTwigFunction::escapeFilter($twig, $key, 'js'), 'Failed to escape: ' . $key);
            }
        } finally {
            if ($previousInternalEncoding !== false) {
                mb_internal_encoding($previousInternalEncoding);
            }
        }
    }

    public function testJavascriptEscapingReturnsStringIfZeroLength(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        static::assertEquals('', SwTwigFunction::escapeFilter($twig, '', 'js'));
    }

    public function testJavascriptEscapingReturnsStringIfContainsOnlyDigits(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        static::assertEquals('123', SwTwigFunction::escapeFilter($twig, '123', 'js'));
    }

    public function testCssEscapingConvertsSpecialChars(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        foreach ($this->cssSpecialChars as $key => $value) {
            static::assertEquals($value, SwTwigFunction::escapeFilter($twig, $key, 'css'), 'Failed to escape: ' . $key);
        }
    }

    public function testCssEscapingReturnsStringIfZeroLength(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        static::assertEquals('', SwTwigFunction::escapeFilter($twig, '', 'css'));
    }

    public function testCssEscapingReturnsStringIfContainsOnlyDigits(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        static::assertEquals('123', SwTwigFunction::escapeFilter($twig, '123', 'css'));
    }

    public function testUrlEscapingConvertsSpecialChars(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        foreach ($this->urlSpecialChars as $key => $value) {
            static::assertEquals($value, SwTwigFunction::escapeFilter($twig, $key, 'url'), 'Failed to escape: ' . $key);
        }
    }

    /**
     * Range tests to confirm escaped range of characters is within OWASP recommendation.
     */
    /**
     * Only testing the first few 2 ranges on this prot. function as that's all these
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
        static::assertEquals($expected, $result);
    }

    public function testJavascriptEscapingEscapesOwaspRecommendedRanges(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        $immune = [',', '.', '_']; // Exceptions to escaping ranges
        for ($chr = 0; $chr < 0xFF; ++$chr) {
            if ($chr >= 0x30 && $chr <= 0x39
                || $chr >= 0x41 && $chr <= 0x5A
                || $chr >= 0x61 && $chr <= 0x7A) {
                $literal = $this->codepointToUtf8($chr);
                static::assertEquals($literal, SwTwigFunction::escapeFilter($twig, $literal, 'js'));
            } else {
                $literal = $this->codepointToUtf8($chr);
                if (\in_array($literal, $immune, true)) {
                    static::assertEquals($literal, SwTwigFunction::escapeFilter($twig, $literal, 'js'));
                } else {
                    static::assertNotEquals(
                        $literal,
                        SwTwigFunction::escapeFilter($twig, $literal, 'js'),
                        "$literal should be escaped!"
                    );
                }
            }
        }
    }

    public function testHtmlAttributeEscapingEscapesOwaspRecommendedRanges(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        $immune = [',', '.', '-', '_']; // Exceptions to escaping ranges
        for ($chr = 0; $chr < 0xFF; ++$chr) {
            if ($chr >= 0x30 && $chr <= 0x39
                || $chr >= 0x41 && $chr <= 0x5A
                || $chr >= 0x61 && $chr <= 0x7A) {
                $literal = $this->codepointToUtf8($chr);
                static::assertEquals($literal, SwTwigFunction::escapeFilter($twig, $literal, 'html_attr'));
            } else {
                $literal = $this->codepointToUtf8($chr);
                if (\in_array($literal, $immune, true)) {
                    static::assertEquals($literal, SwTwigFunction::escapeFilter($twig, $literal, 'html_attr'));
                } else {
                    static::assertNotEquals(
                        $literal,
                        SwTwigFunction::escapeFilter($twig, $literal, 'html_attr'),
                        "$literal should be escaped!"
                    );
                }
            }
        }
    }

    public function testCssEscapingEscapesOwaspRecommendedRanges(): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        // CSS has no exceptions to escaping ranges
        for ($chr = 0; $chr < 0xFF; ++$chr) {
            if ($chr >= 0x30 && $chr <= 0x39
                || $chr >= 0x41 && $chr <= 0x5A
                || $chr >= 0x61 && $chr <= 0x7A) {
                $literal = $this->codepointToUtf8($chr);
                static::assertEquals($literal, SwTwigFunction::escapeFilter($twig, $literal, 'css'));
            } else {
                $literal = $this->codepointToUtf8($chr);
                static::assertNotEquals(
                    $literal,
                    SwTwigFunction::escapeFilter($twig, $literal, 'css'),
                    "$literal should be escaped!"
                );
            }
        }
    }

    /**
     * @param string|int|null $string
     */
    #[DataProvider('provideCustomEscaperCases')]
    #[RunInSeparateProcess]
    public function testCustomEscaper(string $expected, $string, string $strategy): void
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));

        $escapeRuntime = $twig->getRuntime(EscaperRuntime::class);
        $escapeRuntime->setEscaper('foo', 'Shopware\Tests\Unit\Core\Framework\Adapter\Twig\foo_escaper_for_test');

        static::assertSame($expected, SwTwigFunction::escapeFilter($twig, $string, $strategy));
    }

    /**
     * @return array<int, array<int, int|string|null>>
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

    #[RunInSeparateProcess]
    public function testUnknownCustomEscaper(): void
    {
        $this->expectException(RuntimeError::class);

        SwTwigFunction::escapeFilter(new Environment($this->createMock(LoaderInterface::class)), 'foo', 'bar');
    }

    /**
     * @param array<string, string> $safeClasses
     */
    #[DataProvider('provideObjectsForEscaping')]
    public function testObjectEscaping(string $escapedHtml, string $escapedJs, array $safeClasses): void
    {
        $obj = new Extension_TestClass();
        $twig = new Environment($this->createMock(LoaderInterface::class));

        $escapeRuntime = $twig->getRuntime(EscaperRuntime::class);
        $escapeRuntime->setSafeClasses($safeClasses);

        static::assertSame($escapedHtml, SwTwigFunction::escapeFilter($twig, $obj, 'html', null, true));
        static::assertSame($escapedJs, SwTwigFunction::escapeFilter($twig, $obj, 'js', null, true));
    }

    /**
     * @return array<int, array<int, array<string, array<int, string>>|string>>
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
     * Convert a Unicode Codepoint to a literal UTF-8 character.
     *
     * @param int $codepoint Unicode codepoint in hex notation
     *
     * @return string UTF-8 literal string
     */
    protected function codepointToUtf8($codepoint): string
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

        throw new \Exception('Codepoint requested outside of Unicode range.');
    }
}

function foo_escaper_for_test(string $string): string
{
    return strtoupper($string);
}

/**
 * @internal
 */
interface Extension_SafeHtmlInterface
{
}
/**
 * @internal
 */
class Extension_TestClass implements Extension_SafeHtmlInterface, \Stringable
{
    public function __toString(): string
    {
        return '<br />';
    }
}
