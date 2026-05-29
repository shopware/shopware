<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Storefront\Theme\Extension\StorefrontExtensionThemeIdResolver;

/**
 * @internal
 */
#[CoversClass(StorefrontExtensionThemeIdResolver::class)]
class StorefrontExtensionThemeIdResolverTest extends TestCase
{
    #[TestDox('resolveThemeIdByTechnicalName() searches the theme repository by technicalName and returns the first id')]
    public function testResolveThemeIdByTechnicalNameReturnsFirstMatchingId(): void
    {
        $context = Context::createDefaultContext();
        $expectedId = 'theme-id-123';

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->with(
                static::callback(function (Criteria $criteria): bool {
                    $filters = $criteria->getFilters();
                    static::assertCount(1, $filters);
                    static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                    static::assertSame('technicalName', $filters[0]->getField());
                    static::assertSame('SwagTheme', $filters[0]->getValue());

                    return true;
                }),
                $context,
            )
            ->willReturn(new IdSearchResult(1, [$expectedId => ['primaryKey' => $expectedId, 'data' => []]], new Criteria(), $context));

        $resolver = new StorefrontExtensionThemeIdResolver($repository);

        static::assertSame($expectedId, $resolver->resolveThemeIdByTechnicalName('SwagTheme', $context));
    }

    #[TestDox('resolveThemeIdByTechnicalName() returns null when no theme matches')]
    public function testResolveThemeIdByTechnicalNameReturnsNullWhenMissing(): void
    {
        $context = Context::createDefaultContext();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->willReturn(new IdSearchResult(0, [], new Criteria(), $context));

        $resolver = new StorefrontExtensionThemeIdResolver($repository);

        static::assertNull($resolver->resolveThemeIdByTechnicalName('Missing', $context));
    }
}
