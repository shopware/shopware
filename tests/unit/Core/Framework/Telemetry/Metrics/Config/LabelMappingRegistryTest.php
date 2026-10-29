<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfigLoader;
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

    public function testAppClaimsAreConsultedAfterConfigAndCodeLevelRules(): void
    {
        $registry = new LabelMappingRegistry(
            ['groups' => ['core_key' => 'core']],
            'other',
            $this->appLoader(['groups' => ['core_key' => 'swag_app', 'code_key' => 'swag_app', 'swag_*' => 'swag_app']]),
        );
        $code = static fn (string $key): ?string => $key === 'code_key' ? 'code' : null;

        // declared config and the code-level rule shadow app claims for the same key ...
        static::assertSame('core', $registry->resolve('groups', 'core_key', $code));
        static::assertSame('code', $registry->resolve('groups', 'code_key', $code));
        // ... while keys nobody else knows resolve to the app
        static::assertSame('swag_app', $registry->resolve('groups', 'swag_thing', $code));
        static::assertSame('other', $registry->resolve('groups', 'unclaimed', $code));
    }

    public function testDeclaredPrefixShadowsAppExactKey(): void
    {
        $registry = new LabelMappingRegistry(
            ['groups' => ['frontend.account*' => 'customer']],
            'other',
            $this->appLoader(['groups' => ['frontend.account.special' => 'swag_app']]),
        );

        // tier order beats match specificity: declared config shadows the whole keyspace it covers
        static::assertSame('customer', $registry->resolve('groups', 'frontend.account.special'));
    }

    public function testDisabledAppContributionsIgnoreClaims(): void
    {
        $registry = new LabelMappingRegistry(
            ['groups' => []],
            'other',
            $this->appLoader(['groups' => ['swag_*' => 'swag_app']]),
            null,
            false,
        );

        static::assertSame('other', $registry->resolve('groups', 'swag_thing'));
    }

    public function testAppClaimsAreLoadedOnFirstMissOnly(): void
    {
        $loaded = new \stdClass();
        $loaded->count = 0;
        $loader = static::createStub(AppTelemetryConfigLoader::class);
        $loader->method('load')->willReturn(['groups' => ['swag_*' => 'swag_app']]);

        $registry = new LabelMappingRegistry(
            ['groups' => ['core_key' => 'core']],
            'other',
            function () use ($loaded, $loader): AppTelemetryConfigLoader {
                ++$loaded->count;

                return $loader;
            },
        );

        static::assertSame('core', $registry->resolve('groups', 'core_key'));
        static::assertSame(0, $loaded->count);

        static::assertSame('swag_app', $registry->resolve('groups', 'swag_thing'));
        static::assertSame('swag_app', $registry->resolve('groups', 'swag_other'));
        static::assertSame(1, $loaded->count);
    }

    /**
     * @param array<string, array<string, string>> $claims
     *
     * @return \Closure(): AppTelemetryConfigLoader
     */
    private function appLoader(array $claims): \Closure
    {
        $loader = static::createStub(AppTelemetryConfigLoader::class);
        $loader->method('load')->willReturn($claims);

        return static fn (): AppTelemetryConfigLoader => $loader;
    }
}
