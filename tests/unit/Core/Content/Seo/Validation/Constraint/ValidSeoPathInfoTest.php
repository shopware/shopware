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
        yield 'query string' => ['foo/bar?x=1', false];
        yield 'valid percent-escape' => ['caf%C3%A9', false];
        yield 'percent (#13796)' => ['seo/url%/1', true];
        yield 'incomplete percent-escape' => ['seo/url%4/1', true];
        yield 'percent at end' => ['seo/url%', true];
        yield 'fragment' => ['foo/bar#baz', true];
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
        yield 'stray percent collapsed' => ['seo/url%/1', 'seo/url-/1'];
        yield 'fragment collapsed' => ['foo/bar#baz', 'foo/bar-baz'];
        yield 'backslash collapsed' => ['foo\\bar', 'foo-bar'];
        // Query strings are URL-allowed and resolvable, so they survive.
        yield 'query string untouched' => ['foo/bar?x=1', 'foo/bar?x=1'];
        // Valid percent-escapes (rawurlencode output for non-ASCII slug
        // configs) are URL-allowed and must survive untouched.
        yield 'valid percent-escapes untouched' => ['caf%C3%A9', 'caf%C3%A9'];
        // A consecutive run of disallowed characters collapses to one separator.
        yield 'control characters collapsed' => ["foo\0\nbar", 'foo-bar'];
        // Raw spaces are percent-encoded so query-bearing SEO paths stay matchable
        // by the resolver, which only ever sees the space as %20 from the frontend.
        yield 'space in query value encoded' => ['product?colo=red blue', 'product?colo=red%20blue'];
        yield 'multiple spaces encoded' => ['product?a=b c d', 'product?a=b%20c%20d'];
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
