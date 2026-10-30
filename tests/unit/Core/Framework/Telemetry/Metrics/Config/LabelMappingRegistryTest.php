<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LabelMappingRegistry::class)]
class LabelMappingRegistryTest extends TestCase
{
    public function testExactMatch(): void
    {
        $registry = new LabelMappingRegistry(['groups' => ['swag_blog_post' => 'blog']], 'other');

        static::assertSame('blog', $registry->resolve('groups', 'swag_blog_post'));
    }

    public function testPrefixMatch(): void
    {
        $registry = new LabelMappingRegistry(['groups' => ['swag_blog_*' => 'blog']], 'other');

        static::assertSame('blog', $registry->resolve('groups', 'swag_blog_comment'));
    }

    public function testPrefixDoesNotMatchWithoutTrailingStar(): void
    {
        $registry = new LabelMappingRegistry(['groups' => ['swag_blog' => 'blog']], 'other');

        static::assertSame('other', $registry->resolve('groups', 'swag_blog_comment'));
    }

    public function testExactBeatsPrefix(): void
    {
        $registry = new LabelMappingRegistry(['groups' => [
            'swag_blog_*' => 'blog',
            'swag_blog_banner' => 'marketing',
        ]], 'other');

        static::assertSame('marketing', $registry->resolve('groups', 'swag_blog_banner'));
    }

    public function testLongestPrefixWins(): void
    {
        $registry = new LabelMappingRegistry(['groups' => [
            'frontend.account*' => 'customer',
            'frontend.account.login*' => 'auth',
        ]], 'other');

        static::assertSame('auth', $registry->resolve('groups', 'frontend.account.login.page'));
        static::assertSame('customer', $registry->resolve('groups', 'frontend.account.profile'));
    }

    public function testConfigEntryBeatsCodeFallback(): void
    {
        $registry = new LabelMappingRegistry(['groups' => ['core_key' => 'config']], 'other');

        $result = $registry->resolve('groups', 'core_key', static fn (string $key): string => 'code');

        static::assertSame('config', $result);
    }

    public function testCodeFallbackConsultedWhenNoEntryMatches(): void
    {
        $registry = new LabelMappingRegistry(['groups' => []], 'other');

        $result = $registry->resolve('groups', 'anything', static fn (string $key): string => 'code');

        static::assertSame('code', $result);
    }

    public function testReplacementValueWhenNothingMatches(): void
    {
        $registry = new LabelMappingRegistry(['groups' => []], 'fallback');

        static::assertSame('fallback', $registry->resolve('groups', 'anything'));
        static::assertSame('fallback', $registry->resolve('groups', 'anything', static fn (string $key): ?string => null));
    }

    public function testUnknownMappingNameFallsThrough(): void
    {
        $registry = new LabelMappingRegistry([], 'other');

        static::assertSame('other', $registry->resolve('nope', 'key'));
        static::assertSame('code', $registry->resolve('nope', 'key2', static fn (string $key): string => 'code'));
    }
}
