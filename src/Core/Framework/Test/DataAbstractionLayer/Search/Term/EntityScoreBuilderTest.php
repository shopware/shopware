<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Term;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;

class EntityScoreBuilderTest extends TestCase
{
    public function testSimplePattern(): void
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new SearchTerm('term', 1),
            'product'
        );

        $queries = $builder->buildScoreQueries($pattern, ScoreBuilderTestDefinition::class, 'test');

        static::assertEquals(
            [
                new ScoreQuery(new EqualsFilter('test.name', 'term'), 100),
                new ScoreQuery(new ContainsFilter('test.name', 'term'), 50),
                new ScoreQuery(new EqualsFilter('test.description', 'term'), 200),
                new ScoreQuery(new ContainsFilter('test.description', 'term'), 100),
                new ScoreQuery(new EqualsFilter('test.nested.name', 'term'), 50),
                new ScoreQuery(new ContainsFilter('test.nested.name', 'term'), 25),
            ],
            $queries
        );
    }

    public function testMultipleTerms(): void
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new SearchTerm('term', 1),
            'product'
        );
        $pattern->addTerm(
            new SearchTerm('test', 0.1)
        );

        $queries = $builder->buildScoreQueries($pattern, ScoreBuilderTestDefinition::class, 'test');

        static::assertEquals(
            [
                new ScoreQuery(new EqualsFilter('test.name', 'term'), 100),
                new ScoreQuery(new ContainsFilter('test.name', 'term'), 50),
                new ScoreQuery(new EqualsFilter('test.name', 'test'), 10),
                new ScoreQuery(new ContainsFilter('test.name', 'test'), 5),

                new ScoreQuery(new EqualsFilter('test.description', 'term'), 200),
                new ScoreQuery(new ContainsFilter('test.description', 'term'), 100),
                new ScoreQuery(new EqualsFilter('test.description', 'test'), 20),
                new ScoreQuery(new ContainsFilter('test.description', 'test'), 10),

                new ScoreQuery(new EqualsFilter('test.nested.name', 'term'), 50),
                new ScoreQuery(new ContainsFilter('test.nested.name', 'term'), 25),
                new ScoreQuery(new EqualsFilter('test.nested.name', 'test'), 5),
                new ScoreQuery(new ContainsFilter('test.nested.name', 'test'), 2.5),
            ],
            $queries
        );
    }

    public function testTranslatedFieldFallback(): void
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new SearchTerm('term', 1),
            'product'
        );

        $queries = $builder->buildScoreQueries($pattern, OnlyTranslatedFieldDefinition::class, 'test');

        static::assertEquals(
            [
                new ScoreQuery(new EqualsFilter('test.name', 'term'), 1),
                new ScoreQuery(new ContainsFilter('test.name', 'term'), 0.5),
            ],
            $queries
        );
    }
}

class ScoreBuilderTestDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'test';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new SearchRanking(100)),
            (new StringField('description', 'description'))->setFlags(new SearchRanking(200)),
            new StringField('long_description', 'longDescription'),
            (new ManyToOneAssociationField('nested', 'nested_id', NestedDefinition::class, true))->setFlags(new SearchRanking(0.5)),
        ]);
    }
}

class NestedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'nested';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new SearchRanking(100)),
        ]);
    }
}

class OnlyTranslatedFieldDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'translated';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TranslatedField('name'),
        ]);
    }
}
