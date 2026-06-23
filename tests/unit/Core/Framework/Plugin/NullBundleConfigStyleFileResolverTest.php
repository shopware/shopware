<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\NullBundleConfigStyleFileResolver;

/**
 * @internal
 */
#[CoversClass(NullBundleConfigStyleFileResolver::class)]
class NullBundleConfigStyleFileResolverTest extends TestCase
{
    #[TestDox('resolveStyleFiles() always returns an empty array regardless of inputs')]
    public function testResolveStyleFilesReturnsEmptyArray(): void
    {
        $resolver = new NullBundleConfigStyleFileResolver();

        static::assertSame([], $resolver->resolveStyleFiles('SwagPlugin', 'custom/plugins/SwagPlugin'));
        static::assertSame([], $resolver->resolveStyleFiles('', ''));
    }
}
