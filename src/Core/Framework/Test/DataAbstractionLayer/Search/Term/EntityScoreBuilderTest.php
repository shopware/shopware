<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Term;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
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
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class EntityScoreBuilderTest extends TestCase
{
    use KernelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    private EntityDefinition $testDefinition;

    private EntityDefinition $testDefinitionTranslated;

    private EntityDefinition $onlyDateFieldDefinition;

    private Context $context;

    private EntityDefinition $shouldTokenizeDefinition;

    private EntityDefinition $shouldNotTokenizeDefinition;

    protected function setUp(): void
    {
        $this->testDefinition = $this->registerDefinition(ScoreBuilderTestDefinition::class, NestedDefinition::class);
        $this->testDefinitionTranslated = $this->registerDefinition(OnlyTranslatedFieldDefinition::class);
        $this->onlyDateFieldDefinition = $this->registerDefinition(OnlyDateFieldDefinition::class);
        $this->shouldTokenizeDefinition = $this->registerDefinition(ShouldTokenizeFieldDefinition::class);
        $this->shouldNotTokenizeDefinition = $this->registerDefinition(ShouldNotTokenizeFieldDefinition::class);
        $this->context = Context::createDefaultContext();
    }

    /**
     * @dataProvider validDateTerms
     */
    public function testValidDateTerms(string $dateTerm): void
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new SearchTerm($dateTerm, 1),
            'product'
        );

        $queries = $builder->buildScoreQueries($pattern, $this->onlyDateFieldDefinition, 'test', $this->context);

        static::assertEquals(
            [
                new ScoreQuery(new EqualsFilter('test.dateTime', $dateTerm), 100),
                new ScoreQuery(new ContainsFilter('test.dateTime', $dateTerm), 50),
            ],
            $queries
        );
    }

    /**
     * @dataProvider inValidDateTerms
     */
    public function testInValidDateTerms(string $dateTerm): void
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(
            new SearchTerm($dateTerm, 1),
            'product'
        );

        $queries = $builder->buildScoreQueries($pattern, $this->onlyDateFieldDefinition, 'test', $this->context);

        static::assertNotEquals(
            [
                new ScoreQuery(new EqualsFilter('test.dateTime', $dateTerm), 100),
                new ScoreQuery(new ContainsFilter('test.dateTime', $dateTerm), 50),
            ],
            $queries
        );
    }

    /**
     * @dataProvider searchCustomerEmail
     */
    public function testSearchTokenizeTerm(bool $tokenize, string $term): void
    {
        $builder = new EntityScoreQueryBuilder();

        $pattern = new SearchPattern(new SearchTerm($term, 1));

        $pattern->addTerm(new SearchTerm('johndoe', 0.5));
        $pattern->addTerm(new SearchTerm('example', 0.3));
        $pattern->addTerm(new SearchTerm('com', 0.2));

        $definition = $tokenize ? $this->shouldTokenizeDefinition : $this->shouldNotTokenizeDefinition;

        $queries = $builder->buildScoreQueries($pattern, $definition, 'test', $this->context);

        if (!$tokenize) {
            static::assertCount(2, $queries);
            static::assertEquals([
                new ScoreQuery(new EqualsFilter('test.email', $term), 100),
                new ScoreQuery(new ContainsFilter('test.email', $term), 50),
            ], $queries);
        } else {
            static::assertCount(8, $queries);
        }
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

        $context = new Context(new SalesChannelApiSource(TestDefaults::SALES_CHANNEL));
        $queries = $builder->buildScoreQueries($pattern, $this->testDefinition, 'test', $context);

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

    public static function searchCustomerEmail()
    {
        $shouldTokenize = true;

        return [
            'query should tokenzie' => [$shouldTokenize, 'johndoe@example.com'],
            'query should not tokenzie' => [!$shouldTokenize, 'johndoe@example.com'],
        ];
    }

    public static function inValidDateTerms()
    {
        return [
            'query should not be return abc123' => ['abc123'],
            'query should not be return 2020' => ['2020'],
            'query should not be return 2020-01-01' => ['2020-1-01'],
            'query should not be return 1900-12-12' => ['1900-12-1'],
            'query should not be return 1900-12-21' => ['1999-31-31'],
            'query should not be return 2012-02-28' => ['2012-02-31'],
            'query should not be return 2012-12-01 12:12:12' => ['2012-12-01 12:12:12'],
        ];
    }

    public static function validDateTerms()
    {
        return [
            'query should be return 2020-01-01' => ['2020-01-01'],
            'query should be return 1900-12-12' => ['1900-12-01'],
            'query should be return 1900-12-21' => ['1999-12-31'],
            'query should be return 2012-02-28' => ['2012-02-28'],
        ];
    }
}

/**
 * @internal
 */
class ScoreBuilderTestDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'test';

    public function getEntityName(): string
    {
        return 'test';
    }

    public function since(): string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new SearchRanking(100)),
            (new StringField('description', 'description'))->addFlags(new SearchRanking(200)),
            (new StringField('long_description', 'longDescription'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('nested', 'nested_id', NestedDefinition::class, 'id', true))->addFlags(new ApiAware(), new SearchRanking(0.5)),
        ]);
    }
}

/**
 * @internal
 */
class NestedDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'nested';

    public function getEntityName(): string
    {
        return 'nested';
    }

    public function since(): string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new SearchRanking(100)),
        ]);
    }
}

/**
 * @internal
 */
class OnlyTranslatedFieldDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'translated';

    public function getEntityName(): string
    {
        return 'translated';
    }

    public function since(): string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new TranslatedField('name'))->addFlags(new ApiAware()),
        ]);
    }
}

/**
 * @internal
 */
class OnlyDateFieldDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'dates';

    public function getEntityName(): string
    {
        return 'dates';
    }

    public function since(): string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new DateTimeField('date_time', 'dateTime'))->addFlags(new ApiAware(), new SearchRanking(100)),
        ]);
    }
}

/**
 * @internal
 */
class ShouldTokenizeFieldDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'only_tokenize_field';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): string
    {
        return '6.3.5.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new EmailField('email', 'email'))->addFlags(new SearchRanking(100, true)),
        ]);
    }
}

/**
 * @internal
 */
class ShouldNotTokenizeFieldDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'only_not_tokenize_field';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): string
    {
        return '6.3.5.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new EmailField('email', 'email'))->addFlags(new SearchRanking(100, false)),
        ]);
    }
}
