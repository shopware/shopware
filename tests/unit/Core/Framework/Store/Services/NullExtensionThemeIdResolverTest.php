<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\NullExtensionThemeIdResolver;

/**
 * @internal
 */
#[CoversClass(NullExtensionThemeIdResolver::class)]
class NullExtensionThemeIdResolverTest extends TestCase
{
    #[TestDox('resolveThemeIdByTechnicalName() always returns null regardless of inputs')]
    public function testResolveThemeIdByTechnicalNameReturnsNull(): void
    {
        $resolver = new NullExtensionThemeIdResolver();
        $context = Context::createDefaultContext();

        static::assertNull($resolver->resolveThemeIdByTechnicalName('SwagTheme', $context));
        static::assertNull($resolver->resolveThemeIdByTechnicalName('', $context));
    }
}
