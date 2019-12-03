<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Term;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
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
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class EntityScoreBuilderTest extends TestCase
{
    use KernelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var EntityDefinition
     */
    private $testDefinition;

    /**
     * @var EntityDefinition
     */
    private $testDefinitionTranslated;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->testDefinition = $this->registerDefinition(ScoreBuilderTestDefinition::class, NestedDefinition::class);
        $this->testDefinitionTranslated = $this->registerDefinition(OnlyTranslatedFieldDefinition::class);
        $this->context = Context::createDefaultContext();
    }

    public function testSimplePattern(): void
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new SearchTerm('term', 1),
            'product'
        );

        $queries = $builder->buildScoreQueries($pattern, $this->testDefinition, 'test', $this->context);

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

        $queries = $builder->buildScoreQueries($pattern, $this->testDefinition, 'test', $this->context);

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

        $queries = $builder->buildScoreQueries($pattern, $this->testDefinitionTranslated, 'test', $this->context);

        static::assertEquals(
            [
                new ScoreQuery(new EqualsFilter('test.name', 'term'), 1),
                new ScoreQuery(new ContainsFilter('test.name', 'term'), 0.5),
            ],
            $queries
        );
    }

    public function testReadProtectedFieldIsNotIncludedInSearch(): void
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new SearchTerm('term', 1),
            'product'
        );
        $pattern->addTerm(
            new SearchTerm('test', 0.1)
        );

        $queries = [];

        $this->context->scope(SalesChannelApiSource::class, function () use ($builder, $pattern, &$queries): void {
            $queries = $builder->buildScoreQueries($pattern, $this->testDefinition, 'test', $this->context);
        });

        static::assertEquals(
            [
                new ScoreQuery(new EqualsFilter('test.name', 'term'), 100),
                new ScoreQuery(new ContainsFilter('test.name', 'term'), 50),
                new ScoreQuery(new EqualsFilter('test.name', 'test'), 10),
                new ScoreQuery(new ContainsFilter('test.name', 'test'), 5),

                // test.description is missing because its read protected for the sales channel api source

                new ScoreQuery(new EqualsFilter('test.nested.name', 'term'), 50),
                new ScoreQuery(new ContainsFilter('test.nested.name', 'term'), 25),
                new ScoreQuery(new EqualsFilter('test.nested.name', 'test'), 5),
                new ScoreQuery(new ContainsFilter('test.nested.name', 'test'), 2.5),
            ],
            $queries
        );
    }
}

class ScoreBuilderTestDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'test';

    public function getEntityName(): string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new SearchRanking(100)),
            (new StringField('description', 'description'))->addFlags(new SearchRanking(200), new ReadProtected(SalesChannelApiSource::class)),
            new StringField('long_description', 'longDescription'),
            (new ManyToOneAssociationField('nested', 'nested_id', NestedDefinition::class, 'id', true))->addFlags(new SearchRanking(0.5)),
        ]);
    }
}

class NestedDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'nested';

    public function getEntityName(): string
    {
        return 'nested';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new SearchRanking(100)),
        ]);
    }
}

class OnlyTranslatedFieldDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'translated';

    public function getEntityName(): string
    {
        return 'translated';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TranslatedField('name'),
        ]);
    }
}
