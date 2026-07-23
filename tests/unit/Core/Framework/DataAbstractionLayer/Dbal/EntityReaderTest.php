<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaFieldsResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityReader::class)]
class EntityReaderTest extends TestCase
{
    #[DataProvider('nonExcludableFieldProvider')]
    public function testReadRejectsNonExcludableField(string $field, string $expectedErrorCode): void
    {
        $reader = new EntityReader(
            static::createStub(Connection::class),
            static::createStub(EntityHydrator::class),
            static::createStub(EntityDefinitionQueryHelper::class),
            static::createStub(SqlQueryParser::class),
            static::createStub(CriteriaQueryBuilder::class),
            static::createStub(LoggerInterface::class),
            static::createStub(CriteriaFieldsResolver::class),
        );

        $criteria = new Criteria();
        $criteria->excludeFields([$field]);

        // read() validates the excluded fields before touching the database, so the rejection is
        // observable through the public API without a real connection.
        try {
            $reader->read($this->createDefinition(), $criteria, Context::createDefaultContext());
            static::fail('Expected DataAbstractionLayerException was not thrown');
        } catch (DataAbstractionLayerException $e) {
            static::assertSame($expectedErrorCode, $e->getErrorCode());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonExcludableFieldProvider(): iterable
    {
        yield 'unknown field' => ['doesNotExist', DataAbstractionLayerException::CANNOT_EXCLUDE_UNKNOWN_FIELD];
        yield 'required field' => ['stock', DataAbstractionLayerException::FIELD_CANNOT_BE_EXCLUDED];
        yield 'write-protected field' => ['available', DataAbstractionLayerException::FIELD_CANNOT_BE_EXCLUDED];
    }

    private function createDefinition(): EntityDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [EntityReaderTestDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        return $registry->getByEntityName(EntityReaderTestDefinition::ENTITY_NAME);
    }
}

/**
 * @internal
 */
class EntityReaderTestDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'entity_reader_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            new StringField('description', 'description'),
            (new StringField('stock', 'stock'))->addFlags(new Required()),
            (new StringField('available', 'available'))->addFlags(new WriteProtected()),
        ]);
    }
}
