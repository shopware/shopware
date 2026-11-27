<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Store\ThemeExtensionRemovalValidator;
use Shopware\Storefront\Theme\ThemeCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeExtensionRemovalValidator::class)]
class ThemeExtensionRemovalValidatorTest extends TestCase
{
    private const THEME_ID = 'theme-id';

    public function testExtensionWithoutThemeCanBeRemoved(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class);

        $validator = new ThemeExtensionRemovalValidator(
            StaticEntityRepository::of(ThemeCollection::class, [[]]),
            $salesChannelRepository,
        );

        $validator->validateCanBeRemoved('SwagPlugin', 'extension-id', Context::createDefaultContext());

        static::assertSame([], $salesChannelRepository->searchedCriteria);
    }

    public function testThemeAssignedToASalesChannelCannotBeRemoved(): void
    {
        $validator = new ThemeExtensionRemovalValidator(
            StaticEntityRepository::of(ThemeCollection::class, [[self::THEME_ID]]),
            $this->buildSalesChannelRepository(directlyAssigned: [self::THEME_ID], assignedChildren: []),
        );

        $this->expectExceptionObject(StoreException::extensionThemeStillInUse('extension-id'));

        $validator->validateCanBeRemoved('SwagTheme', 'extension-id', Context::createDefaultContext());
    }

    public function testThemeWithAnAssignedChildThemeCannotBeRemoved(): void
    {
        $validator = new ThemeExtensionRemovalValidator(
            StaticEntityRepository::of(ThemeCollection::class, [[self::THEME_ID]]),
            $this->buildSalesChannelRepository(directlyAssigned: [], assignedChildren: [self::THEME_ID]),
        );

        $this->expectExceptionObject(StoreException::extensionThemeStillInUse('extension-id'));

        $validator->validateCanBeRemoved('SwagTheme', 'extension-id', Context::createDefaultContext());
    }

    public function testUnassignedThemeCanBeRemoved(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [
            $this->buildAggregationResult(directlyAssigned: [], assignedChildren: []),
        ]);

        $validator = new ThemeExtensionRemovalValidator(
            StaticEntityRepository::of(ThemeCollection::class, [[self::THEME_ID]]),
            $salesChannelRepository,
        );

        $validator->validateCanBeRemoved('SwagTheme', 'extension-id', Context::createDefaultContext());

        static::assertCount(1, $salesChannelRepository->searchedCriteria);
    }

    /**
     * @param list<string> $directlyAssigned
     * @param list<string> $assignedChildren
     *
     * @return EntityRepository<SalesChannelCollection>
     */
    private function buildSalesChannelRepository(array $directlyAssigned, array $assignedChildren): EntityRepository
    {
        return StaticEntityRepository::of(SalesChannelCollection::class, [$this->buildAggregationResult($directlyAssigned, $assignedChildren)]);
    }

    /**
     * @param list<string> $directlyAssigned
     * @param list<string> $assignedChildren
     */
    private function buildAggregationResult(array $directlyAssigned, array $assignedChildren): AggregationResultCollection
    {
        $toBucket = static fn (string $themeId): Bucket => new Bucket($themeId, 1, null);

        return new AggregationResultCollection([
            new TermsResult('assigned_theme', array_map($toBucket, $directlyAssigned)),
            new TermsResult('assigned_children', array_map($toBucket, $assignedChildren)),
        ]);
    }
}
