<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFileNameSortCriteriaQueryBuilder;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;

/**
 * @internal
 */
#[CoversClass(MediaFileNameSortCriteriaQueryBuilder::class)]
class MediaFileNameSortCriteriaQueryBuilderTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext(new AdminApiSource(null));
    }

    public function testUsesGeneratedColumnForMediaFileNameSorting(): void
    {
        $criteria = (new Criteria())->addSorting(new FieldSorting('fileName'));
        $query = $this->createQuery();

        $this->createCriteriaQueryBuilder()->addSortings(
            new MediaDefinition(),
            $criteria,
            $criteria->getSorting(),
            $query,
            $this->context
        );

        static::assertSame(['`media`.`file_name_sort_key` ASC'], $query->getOrderByParts());
    }

    public function testUsesGeneratedColumnForPrefixedMediaFileNameSorting(): void
    {
        $criteria = (new Criteria())->addSorting(new FieldSorting('media.fileName'));
        $query = $this->createQuery();

        $this->createCriteriaQueryBuilder()->addSortings(
            new MediaDefinition(),
            $criteria,
            $criteria->getSorting(),
            $query,
            $this->context
        );

        static::assertSame(['`media`.`file_name_sort_key` ASC'], $query->getOrderByParts());
    }

    public function testKeepsNaturalSortingAndDirection(): void
    {
        $criteria = (new Criteria())->addSorting(new FieldSorting('fileName', FieldSorting::DESCENDING, true));
        $query = $this->createQuery();

        $this->createCriteriaQueryBuilder()->addSortings(
            new MediaDefinition(),
            $criteria,
            $criteria->getSorting(),
            $query,
            $this->context
        );

        static::assertSame([
            'LENGTH(`media`.`file_name_sort_key`) DESC',
            '`media`.`file_name_sort_key` DESC',
        ], $query->getOrderByParts());
    }

    public function testWrapsGeneratedColumnWhenQueryIsGrouped(): void
    {
        $criteria = (new Criteria())->addSorting(new FieldSorting('fileName'));
        $query = $this->createQuery();
        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $this->createCriteriaQueryBuilder()->addSortings(
            new MediaDefinition(),
            $criteria,
            $criteria->getSorting(),
            $query,
            $this->context
        );

        static::assertSame(['MIN(`media`.`file_name_sort_key`) ASC'], $query->getOrderByParts());
    }

    public function testKeepsDirectGeneratedColumnForToManyAssociationLimitQuery(): void
    {
        $criteria = (new Criteria())->addSorting(new FieldSorting('fileName'));
        $query = $this->createQuery();
        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);
        $query->addState(EntityReader::TO_MANY_ASSOCIATION_LIMIT_QUERY);

        $this->createCriteriaQueryBuilder()->addSortings(
            new MediaDefinition(),
            $criteria,
            $criteria->getSorting(),
            $query,
            $this->context
        );

        static::assertSame(['`media`.`file_name_sort_key` ASC'], $query->getOrderByParts());
    }

    public function testDelegatesOtherMediaSortingsToDefaultBuilder(): void
    {
        $criteria = (new Criteria())->addSorting(new FieldSorting('fileSize'));
        $query = $this->createQuery();
        $helper = $this->createMock(EntityDefinitionQueryHelper::class);
        $helper
            ->expects($this->once())
            ->method('getFieldAccessor')
            ->with('fileSize', static::isInstanceOf(MediaDefinition::class), MediaDefinition::ENTITY_NAME, $this->context)
            ->willReturn('`media`.`file_size`');

        $this->createCriteriaQueryBuilder($helper)->addSortings(
            new MediaDefinition(),
            $criteria,
            $criteria->getSorting(),
            $query,
            $this->context
        );

        static::assertSame(['`media`.`file_size` ASC'], $query->getOrderByParts());
    }

    public function testDelegatesCountSortingToDefaultBuilder(): void
    {
        $criteria = (new Criteria())->addSorting(new CountSorting('fileName'));
        $query = $this->createQuery();
        $helper = $this->createMock(EntityDefinitionQueryHelper::class);
        $helper
            ->expects($this->once())
            ->method('getFieldAccessor')
            ->with('fileName', static::isInstanceOf(MediaDefinition::class), MediaDefinition::ENTITY_NAME, $this->context)
            ->willReturn('`media`.`file_name`');

        $this->createCriteriaQueryBuilder($helper)->addSortings(
            new MediaDefinition(),
            $criteria,
            $criteria->getSorting(),
            $query,
            $this->context
        );

        static::assertSame(['COUNT(`media`.`file_name`) ASC'], $query->getOrderByParts());
    }

    private function createCriteriaQueryBuilder(?EntityDefinitionQueryHelper $helper = null): MediaFileNameSortCriteriaQueryBuilder
    {
        return new MediaFileNameSortCriteriaQueryBuilder(
            $this->createMock(SqlQueryParser::class),
            $helper ?? new EntityDefinitionQueryHelper(),
            $this->createMock(SearchTermInterpreter::class),
            new EntityScoreQueryBuilder(),
            new JoinGroupBuilder(),
            $this->createMock(CriteriaPartResolver::class)
        );
    }

    private function createQuery(): QueryBuilder
    {
        return new QueryBuilder($this->createMock(Connection::class));
    }
}
