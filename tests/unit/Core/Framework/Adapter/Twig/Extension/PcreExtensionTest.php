<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\PcreExtension;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(PcreExtension::class)]
class PcreExtensionTest extends TestCase
{
    #[DataProvider('replaceCases')]
    public function testReplaces(string $template, string $expected): void
    {
        $extension = new PcreExtension();

        $env = new Environment(new ArrayLoader(['test' => $template]));
        $env->addExtension($extension);

        static::assertSame($expected, $env->render('test'));
    }

    /**
     * @return iterable<array-key, array{string, string}>
     */
    public static function replaceCases(): iterable
    {
        yield 'replaces' => [
            '{{ "foo"|preg_replace("/foo/", "bar") }}',
            'bar',
        ];

        yield 'not matching' => [
            '{{ "foo"|preg_replace("/baz/", "bar") }}',
            'foo',
        ];
    }

    public function testReplaceInvalidRegex(): void
    {
        $extension = new PcreExtension();

        $env = new Environment(new ArrayLoader(['test' => '{{ "foo"|preg_replace("A", "bar") }}']));
        $env->addExtension($extension);

        static::expectException(RuntimeError::class);

        $env->render('test');
    }

    #[DataProvider('matchCases')]
    public function testMatch(string $template, string $expected): void
    {
        $extension = new PcreExtension();

        $env = new Environment(new ArrayLoader(['test' => $template]));
        $env->addExtension($extension);

        static::assertSame($expected, $env->render('test'));
    }

    /**
     * @return iterable<array-key, array{string, string}>
     */
    public static function matchCases(): iterable
    {
        yield 'matches' => [
            '{% if preg_match("foo", "/foo/") %}yes{% else %}no{% endif %}',
            'yes',
        ];

        yield 'not matches' => [
            '{% if preg_match("foo", "/baz/") %}yes{% else %}no{% endif %}',
            'no',
        ];
    }

    public function testMatchException(): void
    {
        $extension = new PcreExtension();

        $env = new Environment(new ArrayLoader(['test' => '{{ preg_match("foo", "A") }}']));
        $env->addExtension($extension);

        static::expectException(RuntimeError::class);
        $env->render('test');
    }
}
