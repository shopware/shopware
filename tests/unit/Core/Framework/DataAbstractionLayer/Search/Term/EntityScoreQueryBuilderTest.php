<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Term;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityScoreQueryBuilder::class)]
class EntityScoreQueryBuilderTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [
                HiddenSearchRankingFieldDefinition::class,
                HiddenTranslatedFieldDefinition::class,
                HiddenStringFieldDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testASearchRankingFieldHiddenFromTheApiIsNotSearched(): void
    {
        $definition = $this->registry->get(HiddenSearchRankingFieldDefinition::class);

        static::assertEquals(
            [
                new ScoreQuery(new EqualsFilter('test.name', 'term'), 100),
                new ScoreQuery(new ContainsFilter('test.name', 'term'), 50),
            ],
            $this->buildQueries($definition)
        );
    }

    public function testATranslatedFieldHiddenFromTheApiIsNotSearched(): void
    {
        $definition = $this->registry->get(HiddenTranslatedFieldDefinition::class);

        static::assertEquals(
            [
                new ScoreQuery(new EqualsFilter('test.code', 'term'), 1),
                new ScoreQuery(new ContainsFilter('test.code', 'term'), 0.5),
            ],
            $this->buildQueries($definition)
        );
    }

    public function testAStringFieldHiddenFromTheApiIsNotSearched(): void
    {
        $definition = $this->registry->get(HiddenStringFieldDefinition::class);

        static::assertEquals(
            [
                new ScoreQuery(new EqualsFilter('test.code', 'term'), 1),
                new ScoreQuery(new ContainsFilter('test.code', 'term'), 0.5),
            ],
            $this->buildQueries($definition)
        );
    }

    /**
     * @return array<ScoreQuery>
     */
    private function buildQueries(EntityDefinition $definition): array
    {
        $pattern = new SearchPattern(new SearchTerm('term', 1));

        return (new EntityScoreQueryBuilder())->buildScoreQueries($pattern, $definition, 'test', new Context(new AdminApiSource(null)));
    }
}

/**
 * @internal
 */
class HiddenSearchRankingFieldDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'hidden_search_ranking';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('secret', 'secret'))->removeFlag(ApiAware::class)->addFlags(new SearchRanking(200)),
            (new StringField('name', 'name'))->addFlags(new SearchRanking(100)),
        ]);
    }
}

/**
 * @internal
 */
class HiddenTranslatedFieldDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'hidden_translated';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new TranslatedField('name'))->removeFlag(ApiAware::class),
            new StringField('code', 'code'),
        ]);
    }
}

/**
 * @internal
 */
class HiddenStringFieldDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'hidden_string';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('secret', 'secret'))->removeFlag(ApiAware::class),
            new StringField('code', 'code'),
        ]);
    }
}
