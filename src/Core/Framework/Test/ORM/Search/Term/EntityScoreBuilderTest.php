<?php declare(strict_types=1);

namespace Shopware\Framework\Test\ORM\Search\Term;

use PHPUnit\Framework\TestCase;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Search\Query\MatchQuery;
use Shopware\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Framework\ORM\Search\Term\SearchPattern;
use Shopware\Framework\ORM\Search\Term\SearchTerm;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;

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
        $builder = new \Shopware\Framework\ORM\Search\Term\EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new \Shopware\Framework\ORM\Search\Term\SearchTerm('term', 1)
        );
        $pattern->addTerm(
            new \Shopware\Framework\ORM\Search\Term\SearchTerm('test', 0.1)
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

    public static function getFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new StringField('name', 'name'))->setFlags(new SearchRanking(100)),
            (new StringField('description', 'description'))->setFlags(new SearchRanking(200)),
            new StringField('long_description', 'longDescription'),
            (new ManyToOneAssociationField('nested', 'nested_id', NestedDefinition::class, true))->setFlags(new SearchRanking(0.5)),
        ]);
    }

    public static function getRepositoryClass(): string
    {
        return '';
    }

    public static function getBasicCollectionClass(): string
    {
        return '';
    }

    public static function getBasicStructClass(): string
    {
        return '';
    }

    public static function getWrittenEventClass(): string
    {
        return '';
    }

    public static function getDeletedEventClass(): string
    {
        return '';
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return '';
    }
}

class NestedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'nested';
    }

    public static function getFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new StringField('name', 'name'))->setFlags(new SearchRanking(100)),
        ]);
    }

    public static function getRepositoryClass(): string
    {
        return '';
    }

    public static function getBasicCollectionClass(): string
    {
        return '';
    }

    public static function getDeletedEventClass(): string
    {
        return '';
    }

    public static function getBasicStructClass(): string
    {
        return '';
    }

    public static function getWrittenEventClass(): string
    {
        return '';
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return '';
    }
}

class OnlyTranslatedFieldDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'translated';
    }

    public static function getFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            new TranslatedField(new StringField('name', 'name')),
        ]);
    }

    public static function getRepositoryClass(): string
    {
        return '';
    }

    public static function getBasicCollectionClass(): string
    {
        return '';
    }

    public static function getBasicStructClass(): string
    {
        return '';
    }

    public static function getWrittenEventClass(): string
    {
        return '';
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return '';
    }

    public static function getDeletedEventClass(): string
    {
        return '';
    }
}
