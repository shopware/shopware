<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;

/**
 * @internal
 */
#[CoversClass(RobotsDirective::class)]
class RobotsDirectiveTest extends TestCase
{
    #[DataProvider('providePathBasedCases')]
    public function testIsPathBased(string $type, bool $expected): void
    {
        static::assertSame($expected, RobotsDirective::isPathBased($type));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function providePathBasedCases(): array
    {
        return [
            'allow' => ['Allow', true],
            'disallow' => ['Disallow', true],
            'allow lowercase' => ['allow', true],
            'disallow lowercase' => ['disallow', true],
            'user-agent' => ['User-agent', false],
            'crawl-delay' => ['Crawl-delay', false],
            'sitemap' => ['Sitemap', false],
        ];
    }

    public function testWithBasePathAppliesPathForPathBasedDirectives(): void
    {
        $directive = new RobotsDirective('Disallow', '/admin/');
        $withBasePath = $directive->withBasePath('/en');

        static::assertSame('Disallow', $withBasePath->type);
        static::assertSame('/en/admin/', $withBasePath->value);
    }

    public function testWithBasePathDoesNotApplyPathForNonPathBasedDirectives(): void
    {
        $directive = new RobotsDirective('Crawl-delay', '10');
        $withBasePath = $directive->withBasePath('/en');

        static::assertSame('Crawl-delay', $withBasePath->type);
        static::assertSame('10', $withBasePath->value);
    }

    public function testWithBasePathNormalizesSlashes(): void
    {
        $directive = new RobotsDirective('Allow', 'widgets/');
        $withBasePath = $directive->withBasePath('en/');

        static::assertSame('/en/widgets/', $withBasePath->value);
    }

    public function testWithBasePathHandlesEmptyBasePath(): void
    {
        $directive = new RobotsDirective('Disallow', '/private/');
        $withBasePath = $directive->withBasePath('');

        static::assertSame('/private/', $withBasePath->value);
    }

    public function testRender(): void
    {
        $directive = new RobotsDirective('Allow', '/public/');

        static::assertSame('Allow: /public/', $directive->render());
    }

    public function testImmutability(): void
    {
        $directive = new RobotsDirective('Disallow', '/admin/');
        $withBasePath = $directive->withBasePath('/en');

        static::assertNotSame($directive, $withBasePath);
        static::assertSame('/admin/', $directive->value);
        static::assertSame('/en/admin/', $withBasePath->value);
    }
}
