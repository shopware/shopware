<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityForeignKeyResolver::class)]
class EntityForeignKeyResolverTest extends TestCase
{
    private Connection&Stub $connection;

    private EntityForeignKeyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::createStub(Connection::class);

        $this->resolver = new EntityForeignKeyResolver(
            $this->connection,
            static::createStub(EntityDefinitionQueryHelper::class)
        );
    }

    #[TestDox('Deleted keys of several tree levels that are equal as numbers are all kept')]
    public function testNestedCascadesKeepKeysThatAreEqualAsNumbers(): void
    {
        $levelOneId = '00000000000000000000000000000001';
        $levelTwoId = '1e000000000000000000000000000000';
        $resultsPerLevel = [[['id' => $levelOneId]], [['id' => $levelTwoId]]];

        $this->connection->method('getDatabasePlatform')->willReturn(new MariaDBPlatform());
        $this->connection->method('executeQuery')->willReturnCallback(
            function () use (&$resultsPerLevel): Result {
                $result = static::createStub(Result::class);
                $result->method('fetchAllAssociative')->willReturn(array_shift($resultsPerLevel) ?? []);

                return $result;
            }
        );

        $definition = new CascadeTreeNodeDefinition();

        new StaticDefinitionInstanceRegistry(
            [$definition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $affected = $this->resolver->getAffectedDeletes(
            $definition,
            [['id' => Uuid::randomHex()]],
            Context::createDefaultContext()
        );

        static::assertSame(['cascade_tree_node' => [$levelOneId, $levelTwoId]], $affected);
    }
}

/**
 * @internal
 */
class CascadeTreeNodeDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'cascade_tree_node';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('parent_id', 'parentId', self::class),
            new ManyToOneAssociationField('parent', 'parent_id', self::class, 'id'),
            (new OneToManyAssociationField('children', self::class, 'parent_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
