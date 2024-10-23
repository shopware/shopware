<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Services\ManyToManyAssociationService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ManyToManyAssociationService::class)]
class ManyToManyAssociationServiceTest extends TestCase
{
    public function testGetMappingIdsForAssociationFields(): void
    {
        $ids = new IdsCollection();
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQL80Platform());
        $connection->expects(static::once())
            ->method('executeQuery')
            ->with(
                'SELECT `referenceColumn`, `localColumn` FROM `ManyToManyEntity` WHERE (`localColumn` IN (:ids)) AND (`product_version_id` = UNHEX(:versionId))',
                [
                    'ids' => [
                        Uuid::fromHexToBytes($ids->get('1')),
                        Uuid::fromHexToBytes($ids->get('2')),
                        Uuid::fromHexToBytes($ids->get('3')),
                    ],
                    'versionId' => Defaults::LIVE_VERSION,
                ]
            )
            ->willReturn(
                new Result(
                    new ArrayResult([
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('1')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-1')),
                        ],
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('1')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-2')),
                        ],
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('2')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-1')),
                        ],
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('3')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-1')),
                        ],
                    ]),
                    $connection
                )
            );

        $service = new ManyToManyAssociationService($connection);

        $mappingDefinition = new MappingDefinition();
        new StaticDefinitionInstanceRegistry(
            [$mappingDefinition],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        /** @phpstan-ignore-next-line We want to mock this */
        $associationField = $this->createMock(ManyToManyAssociationField::class);
        $associationField->method('getPropertyName')
            ->willReturn('propertyName');
        $associationField->method('getMappingLocalColumn')
            ->willReturn('localColumn');
        $associationField->method('getMappingReferenceColumn')
            ->willReturn('referenceColumn');
        $associationField->method('getMappingDefinition')
            ->willReturn($mappingDefinition);

        $result = $service->getMappingIdsForAssociationFields(
            [$associationField],
            [
                ['primaryKeyName' => $ids->get('1')],
                ['primaryKeyName' => $ids->get('2')],
                ['primaryKeyName' => $ids->get('3')],
            ],
            'primaryKeyName'
        );

        static::assertEquals([
            'propertyName' => [
                Uuid::fromHexToBytes($ids->get('1')) => [
                    $ids->get('referenceColumn-1'),
                    $ids->get('referenceColumn-2'),
                ],
                Uuid::fromHexToBytes($ids->get('2')) => [
                    $ids->get('referenceColumn-1'),
                ],
                Uuid::fromHexToBytes($ids->get('3')) => [
                    $ids->get('referenceColumn-1'),
                ],
            ],
        ], $result);
    }
}

/**
 * @internal
 */
class MappingDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'ManyToManyEntity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new PrimaryKey()),
            new ManyToManyAssociationField('manyToMany', MockEntityDefinition::class, ManyToManyMappingEntityDefinition::class, 'manyToMany', 'manyToMany'),
        ]);
    }
}
