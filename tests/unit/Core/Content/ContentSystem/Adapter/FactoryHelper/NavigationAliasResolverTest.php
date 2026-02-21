<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(NavigationAliasResolver::class)]
class NavigationAliasResolverTest extends TestCase
{
    #[DataProvider('resolvesKnownAliasesProvider')]
    #[TestDox('resolves alias to correct category ID')]
    public function testResolvesKnownAliasesToCategoryIds(string $alias, string $expectedCategoryId): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId('nav-cat-id');
        $context->getSalesChannel()->setServiceCategoryId('service-cat-id');
        $context->getSalesChannel()->setFooterCategoryId('footer-cat-id');

        $resolver = new NavigationAliasResolver();

        static::assertSame($expectedCategoryId, $resolver->resolve($alias, $context));
    }

    #[TestDox('returns original ID when value is not a known alias')]
    public function testReturnsOriginalIdWhenNotAnAlias(): void
    {
        $uuid = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId('nav-cat-id');

        $resolver = new NavigationAliasResolver();

        static::assertSame($uuid, $resolver->resolve($uuid, $context));
    }

    #[TestDox('returns alias when service category ID is null')]
    public function testReturnsAliasWhenServiceCategoryIdIsNull(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId('nav-cat-id');
        // serviceCategoryId defaults to null — Generator does not set it

        $resolver = new NavigationAliasResolver();

        static::assertSame('service-navigation', $resolver->resolve('service-navigation', $context));
    }

    #[TestDox('returns alias when footer category ID is null')]
    public function testReturnsAliasWhenFooterCategoryIdIsNull(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId('nav-cat-id');
        // footerCategoryId defaults to null — Generator does not set it

        $resolver = new NavigationAliasResolver();

        static::assertSame('footer-navigation', $resolver->resolve('footer-navigation', $context));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function resolvesKnownAliasesProvider(): \Generator
    {
        yield 'main-navigation resolves to navigationCategoryId' => ['main-navigation', 'nav-cat-id'];
        yield 'service-navigation resolves to serviceCategoryId' => ['service-navigation', 'service-cat-id'];
        yield 'footer-navigation resolves to footerCategoryId' => ['footer-navigation', 'footer-cat-id'];
    }
}
