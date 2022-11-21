<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\CustomField;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\CustomFieldDefinition;

/**
 * @internal
 */
class CustomFieldSetRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreate(): void
    {
        $repo = $this->getContainer()->get('custom_field_set.repository');

        $id = Uuid::randomHex();
        $attributeSet = [
            'id' => $id,
            'name' => 'test set',
            'config' => ['description' => 'test set'],
            'customFields' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'foo.size',
                    'type' => 'int',
                ],
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'foo.description',
                    'type' => 'string',
                ],
            ],
            'relations' => [
                [
                    'entityName' => 'product',
                ],
                [
                    'entityName' => 'order',
                ],
            ],
        ];
        $result = $repo->create([$attributeSet], Context::createDefaultContext());

        $events = $result->getEventByEntityName(CustomFieldSetDefinition::ENTITY_NAME);
        static::assertCount(1, $events->getIds());

        $events = $result->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);
        static::assertCount(2, $events->getIds());

        $events = $result->getEventByEntityName(CustomFieldSetRelationDefinition::ENTITY_NAME);
        static::assertCount(2, $events->getIds());
    }

    public function testSearchId(): void
    {
        $repo = $this->getContainer()->get('custom_field_set.repository');

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $attributeSets = [
            [
                'id' => $id1,
                'name' => 'test set 1',
                'config' => ['description' => 'test 1'],
                'customFields' => [
                    [
                        'id' => Uuid::randomHex(),
                        'name' => 'foo.size',
                        'type' => 'int',
                    ],
                ],
                'relations' => [
                    [
                        'entityName' => 'product',
                    ],
                    [
                        'entityName' => 'order',
                    ],
                ],
            ],
            [
                'id' => $id2,
                'name' => 'test set 2',
                'config' => ['description' => 'test 2'],
                'customFields' => [
                    [
                        'id' => Uuid::randomHex(),
                        'name' => 'taxId',
                        'type' => 'string',
                    ],
                ],
                'relations' => [
                    [
                        'entityName' => 'customer',
                    ],
                ],
            ],
        ];
        $result = $repo->create($attributeSets, Context::createDefaultContext());

        $events = $result->getEventByEntityName(CustomFieldSetDefinition::ENTITY_NAME);
        static::assertCount(2, $events->getIds());

        $events = $result->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);
        static::assertCount(2, $events->getIds());

        $events = $result->getEventByEntityName(CustomFieldSetRelationDefinition::ENTITY_NAME);
        static::assertCount(3, $events->getIds());

        $result = $repo->search(new Criteria([$id2]), Context::createDefaultContext());

        $attributeSet = $result->first();
        static::assertNotNull($attributeSet);

        static::assertEquals($id2, $attributeSet->getId());
        static::assertEquals($attributeSets[1]['config'], $attributeSet->getConfig());
    }

    public function testDelete(): void
    {
        $repo = $this->getContainer()->get('custom_field_set.repository');

        $id = Uuid::randomHex();
        $attrId1 = Uuid::randomHex();
        $attrId2 = Uuid::randomHex();

        $entityId1 = Uuid::randomHex();
        $entityId2 = Uuid::randomHex();

        $attributeSet = [
            'id' => $id,
            'name' => 'test set',
            'config' => ['description' => 'test'],
            'customFields' => [
                [
                    'id' => $attrId1,
                    'name' => 'foo.size',
                    'type' => 'int',
                ],
                [
                    'id' => $attrId2,
                    'name' => 'foo.description',
                    'type' => 'string',
                ],
            ],
            'relations' => [
                [
                    'id' => $entityId1,
                    'entityName' => 'product',
                ],
                [
                    'id' => $entityId2,
                    'entityName' => 'order',
                ],
            ],
        ];
        $repo->create([$attributeSet], Context::createDefaultContext());
        $result = $repo->delete([['id' => $id]], Context::createDefaultContext());

        $event = $result->getEventByEntityName(CustomFieldSetDefinition::ENTITY_NAME);
        static::assertCount(1, $event->getIds());
        static::assertEquals($id, $event->getIds()[0]);

        $event = $result->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);
        static::assertCount(2, $event->getIds());

        $event = $result->getEventByEntityName(CustomFieldSetRelationDefinition::ENTITY_NAME);
        static::assertCount(2, $event->getIds());

        $result = $repo->search(new Criteria([$id]), Context::createDefaultContext());
        static::assertEmpty($result->getIds());
    }

    public function testUpdate(): void
    {
        $repo = $this->getContainer()->get('custom_field_set.repository');

        $id = Uuid::randomHex();
        $attributeSet = [
            'id' => $id,
            'name' => 'test set',
            'config' => ['description' => 'test', 'foo' => 'bar'],
            'customFields' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'foo.size',
                    'type' => 'int',
                ],
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'foo.description',
                    'type' => 'string',
                ],
            ],
            'relations' => [
                [
                    'entityName' => 'product',
                ],
                [
                    'entityName' => 'order',
                ],
            ],
        ];
        $repo->create([$attributeSet], Context::createDefaultContext());

        $update = [
            'id' => $id,
            'name' => 'test set update',
            'config' => ['description' => 'update', 'translatable' => true],
        ];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldSetDefinition::ENTITY_NAME);
        static::assertCount(1, $event->getPayloads());

        $result = $repo->search(new Criteria([$id]), Context::createDefaultContext());
        /** @var CustomFieldSetEntity $set */
        $set = $result->first();
        static::assertEquals($update['config'], $set->getConfig());
    }

    public function testSearchWithAssociations(): void
    {
        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('custom_field_set.repository');

        $id = Uuid::randomHex();
        $nullId = Uuid::randomHex();
        $attributeSets = [
            [
                'id' => $id,
                'name' => 'test set',
                'config' => ['description' => 'test 1'],
                'customFields' => [
                    [
                        'id' => Uuid::randomHex(),
                        'name' => 'foo.size',
                        'type' => 'int',
                    ],
                ],
                'relations' => [
                    [
                        'entityName' => 'product',
                    ],
                    [
                        'entityName' => 'order',
                    ],
                ],
            ],
            [
                'id' => $nullId,
                'name' => 'test set null',
                'config' => ['description' => 'test 1'],
            ],
        ];
        $repo->create($attributeSets, Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('customFields');
        $criteria->addAssociation('relations');

        /** @var CustomFieldSetEntity $first */
        $first = $repo->search($criteria, Context::createDefaultContext())->first();
        static::assertCount(1, $first->getCustomFields()->getElements());
        static::assertCount(2, $first->getRelations()->getElements());

        $criteria = new Criteria([$nullId]);
        $criteria->addAssociation('customFields');
        $criteria->addAssociation('relations');

        /** @var CustomFieldSetEntity $first */
        $first = $repo->search($criteria, Context::createDefaultContext())->first();
        static::assertNotNull($first->getCustomFields());
        static::assertNotNull($first->getRelations());
        static::assertCount(0, $first->getCustomFields());
        static::assertCount(0, $first->getRelations());

        $criteria = new Criteria([$nullId]);
        /** @var CustomFieldSetEntity $first */
        $first = $repo->search($criteria, Context::createDefaultContext())->first();
        static::assertNull($first->getCustomFields());
        static::assertNull($first->getRelations());
    }
}
