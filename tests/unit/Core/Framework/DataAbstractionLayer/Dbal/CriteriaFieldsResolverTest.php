<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaFieldsResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(CriteriaFieldsResolver::class)]
class CriteriaFieldsResolverTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [
                TestDefinition::class,
                RelatedTestDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    /**
     * @param array<int, mixed> $expected
     */
    #[DataProvider('resolveFieldsProvider')]
    public function testResolveFields(Criteria $criteria, array $expected): void
    {
        $resolver = new CriteriaFieldsResolver();

        $result = $resolver->resolve($criteria, $this->registry->get(TestDefinition::class));

        static::assertEquals($expected, $result);
    }

    public static function resolveFieldsProvider(): \Generator
    {
        yield 'empty criteria' => [new Criteria(), []];

        yield 'criteria with association field' => [
            (new Criteria())
                ->addFields(['name', 'relation.name']),
            ['name' => [], 'relation' => ['name' => []]],
        ];

        yield 'criteria with runtime field' => [
            (new Criteria())
                ->addFields(['name', 'variation']),

            ['name' => [], 'variation' => [], 'relation' => ['name' => []]],
        ];
    }
}

/**
 * @internal
 */
class TestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'criteria_fields_resolver_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name'),
            (new StringField('resolved_name', 'resolvedName'))->addFlags(new Runtime()),
            (new ListField('variation', 'variation', StringField::class))->addFlags(new Runtime(['relation.name'])),
            new ManyToOneAssociationField('relation', 'relation_id', RelatedTestDefinition::class, 'id'),
        ]);
    }
}

/**
 * @internal
 */
class RelatedTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'related_criteria_fields_resolver_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name'),
        ]);
    }
}
