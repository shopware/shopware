<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Cache\Http\NoVarySearchDirectives;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-import-type NoVarySearchDirectivesConfig from NoVarySearchDirectives
 */
#[Package('framework')]
#[CoversClass(NoVarySearchDirectives::class)]
class NoVarySearchDirectivesTest extends TestCase
{
    /**
     * @param NoVarySearchDirectivesConfig $config
     */
    #[DataProvider('headerValueProvider')]
    public function testToHeaderValue(array $config, ?string $expected): void
    {
        static::assertSame($expected, NoVarySearchDirectives::fromArray($config)->toHeaderValue());
    }

    /**
     * @return iterable<string, array{0: NoVarySearchDirectivesConfig, 1: string|null}>
     */
    public static function headerValueProvider(): iterable
    {
        yield 'empty config' => [[], null];
        yield 'key order disabled' => [['key_order' => false], null];
        yield 'key order' => [['key_order' => true], 'key-order'];
        yield 'all params' => [['params' => true], 'params'];
        yield 'params disabled' => [['params' => false], null];
        yield 'empty params list' => [['params' => []], null];
        yield 'params list' => [['params' => ['utm_source', 'gclid']], 'params=("utm_source" "gclid")'];
        yield 'single param' => [['params' => ['utm_source']], 'params=("utm_source")'];
        yield 'key order and params list' => [
            ['key_order' => true, 'params' => ['utm_source']],
            'key-order, params=("utm_source")',
        ];
        yield 'all params with except' => [
            ['params' => true, 'except' => ['q']],
            'params, except=("q")',
        ];
        yield 'key order and all params with except' => [
            ['key_order' => true, 'params' => true, 'except' => ['q', 'p']],
            'key-order, params, except=("q" "p")',
        ];
        yield 'except is ignored without params' => [['key_order' => true, 'except' => []], 'key-order'];
        yield 'parameter names allowing url safe characters' => [
            ['params' => ['utm_source', 'a.b', 'a-b', 'a_b', 'a~b', 'a%20b', 'a+b']],
            'params=("utm_source" "a.b" "a-b" "a_b" "a~b" "a%20b" "a+b")',
        ];
    }

    public function testExceptWithoutAllParamsThrows(): void
    {
        self::expectExceptionObject(AdapterException::invalidCachePolicyConfiguration(
            'no_vary_search "except" is only allowed in combination with "params: true"'
        ));

        new NoVarySearchDirectives(params: ['utm_source'], except: ['q']);
    }

    public function testExceptWithoutAnyParamsThrows(): void
    {
        self::expectExceptionObject(AdapterException::invalidCachePolicyConfiguration(
            'no_vary_search "except" is only allowed in combination with "params: true"'
        ));

        new NoVarySearchDirectives(except: ['q']);
    }

    #[DataProvider('invalidParameterNameProvider')]
    public function testInvalidParameterNameThrows(string $name): void
    {
        self::expectExceptionObject(AdapterException::invalidCachePolicyConfiguration(
            \sprintf('no_vary_search contains the invalid query parameter name "%s"', $name)
        ));

        new NoVarySearchDirectives(params: [$name]);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidParameterNameProvider(): iterable
    {
        yield 'whitespace' => ['a b'];
        yield 'double quote' => ['a"b'];
        yield 'backslash' => ['a\\b'];
        yield 'empty' => [''];
        yield 'comma' => ['a,b'];
        yield 'parenthesis' => ['a(b'];
    }

    public function testInvalidParameterNameInExceptThrows(): void
    {
        self::expectExceptionObject(AdapterException::invalidCachePolicyConfiguration(
            'no_vary_search contains the invalid query parameter name "a b"'
        ));

        new NoVarySearchDirectives(params: true, except: ['a b']);
    }

    public function testFromArrayRejectsNonListParams(): void
    {
        self::expectExceptionObject(AdapterException::invalidCachePolicyConfiguration(
            'no_vary_search "params" must be a list of query parameter names'
        ));

        /** @phpstan-ignore argument.type (intentionally invalid configuration) */
        NoVarySearchDirectives::fromArray(['params' => ['a' => 'utm_source']]);
    }

    public function testFromArrayRejectsNonStringParams(): void
    {
        self::expectExceptionObject(AdapterException::invalidCachePolicyConfiguration(
            'no_vary_search "params" must only contain strings'
        ));

        /** @phpstan-ignore argument.type (intentionally invalid configuration) */
        NoVarySearchDirectives::fromArray(['params' => [1, 2]]);
    }

    public function testFromArray(): void
    {
        $directives = NoVarySearchDirectives::fromArray([
            'key_order' => true,
            'params' => true,
            'except' => ['q'],
        ]);

        static::assertTrue($directives->keyOrder);
        static::assertTrue($directives->params);
        static::assertSame(['q'], $directives->except);
    }

    public function testFromArrayDefaults(): void
    {
        $directives = NoVarySearchDirectives::fromArray([]);

        static::assertNull($directives->keyOrder);
        static::assertNull($directives->params);
        static::assertSame([], $directives->except);
    }

    public function testFromArrayIgnoresIncludeIgnoredUrlParameters(): void
    {
        // resolved by CachePolicyProviderFactory, the value object must not choke on it
        $directives = NoVarySearchDirectives::fromArray([
            'key_order' => true,
            'include_ignored_url_parameters' => true,
        ]);

        static::assertTrue($directives->keyOrder);
        static::assertNull($directives->params);
    }

    public function testToArrayRoundTrip(): void
    {
        $config = ['key_order' => true, 'params' => true, 'except' => ['q']];

        static::assertSame($config, NoVarySearchDirectives::fromArray($config)->toArray());
    }

    public function testToArrayOmitsUnsetValues(): void
    {
        static::assertSame([], (new NoVarySearchDirectives())->toArray());
        static::assertSame(['key_order' => true], (new NoVarySearchDirectives(keyOrder: true))->toArray());
    }

    public function testWith(): void
    {
        $directives = new NoVarySearchDirectives(keyOrder: true, params: ['utm_source']);

        $updated = $directives->with(['params' => ['gclid']]);

        static::assertTrue($updated->keyOrder);
        static::assertSame(['gclid'], $updated->params);
        // original stays untouched
        static::assertSame(['utm_source'], $directives->params);
    }
}
