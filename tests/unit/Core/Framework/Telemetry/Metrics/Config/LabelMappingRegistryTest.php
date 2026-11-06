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

    public function testAppEntriesAreUnionedIn(): void
    {
        $registry = new LabelMappingRegistry(
            ['groups' => ['core_key' => 'core']],
            'other',
            $this->appLoader(['groups' => ['swag_*' => 'blog']]),
        );

        static::assertSame('core', $registry->resolve('groups', 'core_key'));
        static::assertSame('blog', $registry->resolve('groups', 'swag_thing'));
    }

    public function testDeclaredEntryBeatsAppEntryWithSameKey(): void
    {
        $registry = new LabelMappingRegistry(
            ['groups' => ['shared_key' => 'declared']],
            'other',
            $this->appLoader(['groups' => ['shared_key' => 'from_app']]),
        );

        static::assertSame('declared', $registry->resolve('groups', 'shared_key'));
    }

    public function testCodeFallbackBeatsAppEntry(): void
    {
        $registry = new LabelMappingRegistry(
            ['groups' => []],
            'other',
            $this->appLoader(['groups' => ['core_key' => 'from_app', 'swag_key' => 'blog']]),
        );

        $code = static fn (string $key): ?string => $key === 'core_key' ? 'code' : null;

        // an app entry must never relabel a key the resolver's code-level rule claims ...
        static::assertSame('code', $registry->resolve('groups', 'core_key', $code));
        // ... but still applies to keys the code-level rule does not know
        static::assertSame('blog', $registry->resolve('groups', 'swag_key', $code));
    }

    public function testDeclaredPrefixBeatsAppExactKey(): void
    {
        $registry = new LabelMappingRegistry(
            ['groups' => ['frontend.account*' => 'customer']],
            'other',
            $this->appLoader(['groups' => ['frontend.account.special' => 'from_app']]),
        );

        // tier order beats match specificity: declared config shadows the whole keyspace it covers
        static::assertSame('customer', $registry->resolve('groups', 'frontend.account.special'));
    }

    public function testNoNewValuesPolicyFiltersPerEntry(): void
    {
        $registry = new LabelMappingRegistry(
            ['groups' => ['core_key' => 'product']],
            'other',
            $this->appLoader(['groups' => [
                'swag_bundle_*' => 'product', // groups into the declared taxonomy → accepted
                'swag_blog_*' => 'blog',      // introduces a new label value → dropped
            ]]),
            null,
            'no-new-values',
        );

        static::assertSame('product', $registry->resolve('groups', 'swag_bundle_thing'));
        static::assertSame('other', $registry->resolve('groups', 'swag_blog_post'));
    }

    public function testNonePolicyIgnoresAppEntries(): void
    {
        $registry = new LabelMappingRegistry(
            ['groups' => ['core_key' => 'product']],
            'other',
            $this->appLoader(['groups' => ['swag_bundle_*' => 'product']]),
            null,
            'none',
        );

        static::assertSame('product', $registry->resolve('groups', 'core_key'));
        static::assertSame('other', $registry->resolve('groups', 'swag_bundle_thing'));
    }

    /**
     * @param array<string, array<string, string>> $mappings
     *
     * @return \Closure(): AppTelemetryConfigLoader
     */
    private function appLoader(array $mappings): \Closure
    {
        $loader = static::createStub(AppTelemetryConfigLoader::class);
        $loader->method('load')->willReturn(['labelValues' => [], 'mappings' => $mappings]);

        return static fn (): AppTelemetryConfigLoader => $loader;
    }
}
