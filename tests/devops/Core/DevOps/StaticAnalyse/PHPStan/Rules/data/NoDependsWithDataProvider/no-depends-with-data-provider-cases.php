<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\NoDependsWithDataProviderFixture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\DependsUsingShallowClone;
use PHPUnit\Framework\TestCase;

class NoDependsWithDataProviderCases extends TestCase
{
    public function testSeed(): string
    {
        return 'seed';
    }

    #[Depends('testSeed')]
    #[DataProvider('provider')]
    public function testForbidden(string $value, string $seed): void
    {
        static::assertNotEmpty($value . $seed);
    }

    // attribute order must not matter, and the *External variants are covered too
    #[DataProviderExternal(ExternalProvider::class, 'provide')]
    #[DependsUsingShallowClone('testSeed')]
    public function testForbiddenReversed(string $value, string $seed): void
    {
        static::assertNotEmpty($value . $seed);
    }

    #[DataProvider('provider')]
    public function testOnlyProvider(string $value): void
    {
        static::assertNotEmpty($value);
    }

    #[Depends('testSeed')]
    public function testOnlyDepends(string $seed): void
    {
        static::assertNotEmpty($seed);
    }

    public static function provider(): \Generator
    {
        yield 'case' => ['a'];
    }
}

class ExternalProvider
{
    public static function provide(): \Generator
    {
        yield 'case' => ['a'];
    }
}
