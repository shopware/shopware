<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ValidSeoPathInfo::class)]
class ValidSeoPathInfoTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function detectionProvider(): iterable
    {
        yield 'plain path' => ['Computers/Laptops', false];
        yield 'dot and tilde' => ['a.b~c', false];
        yield 'percent (#13796)' => ['seo/url%/1', true];
        yield 'fragment' => ['foo/bar#baz', true];
        yield 'query' => ['foo/bar?x=1', true];
        yield 'backslash' => ['foo\\bar', true];
        yield 'control char' => ["foo\0bar", true];
    }

    #[DataProvider('detectionProvider')]
    public function testContainsDisallowedCharacters(string $path, bool $expected): void
    {
        static::assertSame($expected, ValidSeoPathInfo::containsDisallowedCharacters($path));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function sanitizeProvider(): iterable
    {
        yield 'untouched when clean' => ['Computers/Laptops', 'Computers/Laptops'];
        yield 'percent collapsed' => ['seo/url%/1', 'seo/url-/1'];
        yield 'fragment collapsed' => ['foo/bar#baz', 'foo/bar-baz'];
        yield 'query collapsed' => ['foo/bar?x=1', 'foo/bar-x=1'];
        yield 'backslash collapsed' => ['foo\\bar', 'foo-bar'];
        // Only the disallowed characters are replaced; the surrounding
        // alphanumerics survive. The result is router-safe, not a faithful
        // decode of the original `%XX` sequence.
        yield 'percent markers in encoded sequence replaced' => ['caf%C3%A9', 'caf-C3-A9'];
        // A consecutive run of disallowed characters collapses to one separator.
        yield 'control characters collapsed' => ["foo\0\nbar", 'foo-bar'];
    }

    #[DataProvider('sanitizeProvider')]
    public function testSanitize(string $path, string $expected): void
    {
        $sanitized = ValidSeoPathInfo::sanitize($path);

        static::assertSame($expected, $sanitized);
        static::assertFalse(
            ValidSeoPathInfo::containsDisallowedCharacters($sanitized),
            'Sanitised path must no longer contain disallowed characters'
        );
    }
}
