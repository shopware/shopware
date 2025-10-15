<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;

/**
 * @internal
 */
#[CoversClass(RobotsDirective::class)]
class RobotsDirectiveTest extends TestCase
{
    #[DataProvider('providePathBasedCases')]
    public function testIsPathBased(RobotsDirectiveType $type, bool $expected): void
    {
        $directive = new RobotsDirective($type, 'test-value');
        static::assertSame($expected, $directive->isPathBased());
    }

    /**
     * @return array<string, array{RobotsDirectiveType, bool}>
     */
    public static function providePathBasedCases(): array
    {
        return [
            'allow' => [RobotsDirectiveType::ALLOW, true],
            'disallow' => [RobotsDirectiveType::DISALLOW, true],
            'user-agent' => [RobotsDirectiveType::USER_AGENT, false],
            'crawl-delay' => [RobotsDirectiveType::CRAWL_DELAY, false],
            'sitemap' => [RobotsDirectiveType::SITEMAP, false],
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
