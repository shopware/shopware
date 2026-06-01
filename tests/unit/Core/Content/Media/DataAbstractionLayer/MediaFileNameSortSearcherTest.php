<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFileNameSortSearcher;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * @internal
 */
#[CoversClass(MediaFileNameSortSearcher::class)]
class MediaFileNameSortSearcherTest extends TestCase
{
    /**
     * @param list<string> $expectedFields
     */
    #[DataProvider('replaceFileNameSortingProvider')]
    public function testReplacesFileNameSortingBeforeDelegating(Criteria $criteria, array $expectedFields): void
    {
        $decorated = new RecordingEntitySearcher();

        (new MediaFileNameSortSearcher($decorated))->search(
            new MediaDefinition(),
            $criteria,
            Context::createDefaultContext(new AdminApiSource(null))
        );

        static::assertNotNull($decorated->criteria);
        static::assertSame($expectedFields, array_map(
            static fn (FieldSorting $sorting): string => $sorting->getField(),
            $decorated->criteria->getSorting()
        ));
    }

    public function testKeepsOriginalCriteriaUntouchedWhenReplacingSorting(): void
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('fileName'));

        $decorated = new RecordingEntitySearcher();

        (new MediaFileNameSortSearcher($decorated))->search(
            new MediaDefinition(),
            $criteria,
            Context::createDefaultContext(new AdminApiSource(null))
        );

        static::assertNotSame($criteria, $decorated->criteria);
        static::assertNotNull($decorated->criteria);
        static::assertSame('fileName', $criteria->getSorting()[0]->getField());
        static::assertSame('fileNameSortKey', $decorated->criteria->getSorting()[0]->getField());
    }

    public function testKeepsSortingForOtherDefinitions(): void
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('fileName'));

        $decorated = new RecordingEntitySearcher();

        (new MediaFileNameSortSearcher($decorated))->search(
            new ProductDefinition(),
            $criteria,
            Context::createDefaultContext(new AdminApiSource(null))
        );

        static::assertNotNull($decorated->criteria);
        static::assertSame($criteria, $decorated->criteria);
        static::assertSame('fileName', $decorated->criteria->getSorting()[0]->getField());
    }

    public function testKeepsSortingOptionsWhenReplacingFileNameSorting(): void
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('media.fileName', FieldSorting::DESCENDING, true));

        $decorated = new RecordingEntitySearcher();

        (new MediaFileNameSortSearcher($decorated))->search(
            new MediaDefinition(),
            $criteria,
            Context::createDefaultContext(new AdminApiSource(null))
        );

        static::assertNotNull($decorated->criteria);
        $sorting = $decorated->criteria->getSorting()[0];
        static::assertInstanceOf(FieldSorting::class, $sorting);
        static::assertSame('media.fileNameSortKey', $sorting->getField());
        static::assertSame(FieldSorting::DESCENDING, $sorting->getDirection());
        static::assertTrue($sorting->getNaturalSorting());
    }

    /**
     * @return iterable<string, array{Criteria, list<string>}>
     */
    public static function replaceFileNameSortingProvider(): iterable
    {
        yield 'unprefixed media file name sorting uses the internal sort key' => [
            (new Criteria())->addSorting(new FieldSorting('fileName')),
            ['fileNameSortKey'],
        ];

        yield 'prefixed media file name sorting keeps the entity prefix' => [
            (new Criteria())->addSorting(new FieldSorting('media.fileName')),
            ['media.fileNameSortKey'],
        ];

        yield 'other sortings stay untouched' => [
            (new Criteria())->addSorting(new FieldSorting('createdAt')),
            ['createdAt'],
        ];

        yield 'secondary sortings keep their requested order' => [
            (new Criteria())->addSorting(
                new FieldSorting('fileName'),
                new FieldSorting('fileSize')
            ),
            ['fileNameSortKey', 'fileSize'],
        ];

        yield 'count sortings are not rewritten' => [
            (new Criteria())->addSorting(new CountSorting('fileName')),
            ['fileName'],
        ];
    }
}

/**
 * @internal
 */
class RecordingEntitySearcher implements EntitySearcherInterface
{
    public ?Criteria $criteria = null;

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        $this->criteria = $criteria;

        return IdSearchResult::fromIds([], $criteria, $context);
    }
}
