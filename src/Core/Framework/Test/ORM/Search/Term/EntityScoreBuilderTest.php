<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Search\Term;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Search\Query\MatchQuery;
use Shopware\Core\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\ORM\Search\Term\SearchPattern;
use Shopware\Core\Framework\ORM\Search\Term\SearchTerm;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;

class EntityScoreBuilderTest extends TestCase
{
    public function testSimplePattern()
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new SearchTerm('term', 1)
        );

        $queries = $builder->buildScoreQueries($pattern, TestDefinition::class, 'test');

        $this->assertEquals(
            [
                new ScoreQuery(new TermQuery('test.name', 'term'), 100),
                new ScoreQuery(new MatchQuery('test.name', 'term'), 50),
                new ScoreQuery(new TermQuery('test.description', 'term'), 200),
                new ScoreQuery(new MatchQuery('test.description', 'term'), 100),
                new ScoreQuery(new TermQuery('test.nested.name', 'term'), 50),
                new ScoreQuery(new MatchQuery('test.nested.name', 'term'), 25),
            ],
            $queries
        );
    }

    public function testMultipleTerms()
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new SearchTerm('term', 1)
        );
        $pattern->addTerm(
            new SearchTerm('test', 0.1)
        );

        $queries = $builder->buildScoreQueries($pattern, TestDefinition::class, 'test');

        $this->assertEquals(
            [
                new ScoreQuery(new TermQuery('test.name', 'term'), 100),
                new ScoreQuery(new MatchQuery('test.name', 'term'), 50),
                new ScoreQuery(new TermQuery('test.name', 'test'), 10),
                new ScoreQuery(new MatchQuery('test.name', 'test'), 5),

                new ScoreQuery(new TermQuery('test.description', 'term'), 200),
                new ScoreQuery(new MatchQuery('test.description', 'term'), 100),
                new ScoreQuery(new TermQuery('test.description', 'test'), 20),
                new ScoreQuery(new MatchQuery('test.description', 'test'), 10),

                new ScoreQuery(new TermQuery('test.nested.name', 'term'), 50),
                new ScoreQuery(new MatchQuery('test.nested.name', 'term'), 25),
                new ScoreQuery(new TermQuery('test.nested.name', 'test'), 5),
                new ScoreQuery(new MatchQuery('test.nested.name', 'test'), 2.5),
            ],
            $queries
        );
    }

    public function testTranslatedFieldFallback()
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new SearchTerm('term', 1)
        );

        $queries = $builder->buildScoreQueries($pattern, OnlyTranslatedFieldDefinition::class, 'test');

        $this->assertEquals(
            [
                new ScoreQuery(new TermQuery('test.name', 'term'), 1),
                new ScoreQuery(new MatchQuery('test.name', 'term'), 0.5),
            ],
            $queries
        );
    }
}

class TestDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'test';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            new TranslatedField(new StringField('name', 'name')),
        ]);
    }
}
