<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(RobotsDirective::class)]
class RobotsDirectiveTest extends TestCase
{
    #[DataProvider('providePathBasedCases')]
    public function testIsPathBased(RobotsDirectiveType $type, bool $expected, string $value = 'test-value'): void
    {
        $directive = new RobotsDirective($type, $value);
        static::assertSame($expected, $directive->isPathBased());
    }

    /**
     * @return array<string, array{0: RobotsDirectiveType, 1: bool, 2?: string}>
     */
    public static function providePathBasedCases(): array
    {
        return [
            'allow' => [RobotsDirectiveType::ALLOW, true],
            'disallow' => [RobotsDirectiveType::DISALLOW, true],
            'user-agent' => [RobotsDirectiveType::USER_AGENT, false],
            'crawl-delay' => [RobotsDirectiveType::CRAWL_DELAY, false],
            'sitemap' => [RobotsDirectiveType::SITEMAP, false],
            'content-usage' => [RobotsDirectiveType::CONTENT_USAGE, false, 'train-ai=n'],
            'content-usage with path' => [RobotsDirectiveType::CONTENT_USAGE, true, '/blog/ train-ai=n'],
            'content-signal' => [RobotsDirectiveType::CONTENT_SIGNAL, false],
        ];
    }

    public function testWithBasePathAppliesPathForPathBasedDirectives(): void
    {
        $directive = new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/');
        $withBasePath = $directive->withBasePath('/en');

        static::assertSame(RobotsDirectiveType::DISALLOW, $withBasePath->type);
        static::assertSame('/en/admin/', $withBasePath->value);
    }

    public function testWithBasePathDoesNotApplyPathForNonPathBasedDirectives(): void
    {
        $directive = new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10');
        $withBasePath = $directive->withBasePath('/en');

        static::assertSame(RobotsDirectiveType::CRAWL_DELAY, $withBasePath->type);
        static::assertSame('10', $withBasePath->value);
    }

    public function testWithBasePathPrefixesOnlyThePathOfContentUsage(): void
    {
        $directive = new RobotsDirective(RobotsDirectiveType::CONTENT_USAGE, '/blog/ train-ai=n, search=y');
        $withBasePath = $directive->withBasePath('/de');

        static::assertSame(RobotsDirectiveType::CONTENT_USAGE, $withBasePath->type);
        static::assertSame('/de/blog/ train-ai=n, search=y', $withBasePath->value);
    }

    public function testWithBasePathDoesNotApplyPathForContentUsageWithoutPath(): void
    {
        $directive = new RobotsDirective(RobotsDirectiveType::CONTENT_USAGE, 'train-ai=n');

        static::assertSame($directive, $directive->withBasePath('/de'));
    }

    public function testWithBasePathNormalizesSlashes(): void
    {
        $directive = new RobotsDirective(RobotsDirectiveType::ALLOW, 'widgets/');
        $withBasePath = $directive->withBasePath('en/');

        static::assertSame('/en/widgets/', $withBasePath->value);
    }

    public function testWithBasePathHandlesEmptyBasePath(): void
    {
        $directive = new RobotsDirective(RobotsDirectiveType::DISALLOW, '/private/');
        $withBasePath = $directive->withBasePath('');

        static::assertSame('/private/', $withBasePath->value);
    }

    public function testRender(): void
    {
        $directive = new RobotsDirective(RobotsDirectiveType::ALLOW, '/public/');

        static::assertSame('Allow: /public/', $directive->render());
    }

    public function testImmutability(): void
    {
        $directive = new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/');
        $withBasePath = $directive->withBasePath('/en');

        static::assertNotSame($directive, $withBasePath);
        static::assertSame('/admin/', $directive->value);
        static::assertSame('/en/admin/', $withBasePath->value);
    }
}
