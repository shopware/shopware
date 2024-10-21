<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\CustomField;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldDefinition;

/**
 * @internal
 */
class CustomFieldRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CustomFieldCollection>
     */
    private EntityRepository $repo;

    protected function setUp(): void
    {
        $this->repo = $this->getContainer()->get('custom_field.repository');
    }

    public function testCreate(): void
    {
        $id = Uuid::randomHex();
        $attribute = [
            'id' => $id,
            'name' => 'foo_size',
            'type' => 'int',
        ];
        $result = $this->repo->create([$attribute], Context::createDefaultContext());

        $events = $result->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);
        static::assertNotNull($events);

        $payloads = $events->getPayloads();
        static::assertNotEmpty($payloads);

        static::assertEquals($attribute['id'], $payloads[0]['id']);
        static::assertEquals($attribute['name'], $payloads[0]['name']);
        static::assertEquals($attribute['type'], $payloads[0]['type']);
    }

    public function testSearchId(): void
    {
        $sizeId = Uuid::randomHex();
        $descriptionId = Uuid::randomHex();
        $attributes = [
            [
                'id' => $sizeId,
                'name' => 'foo_size',
                'type' => 'int',
                'config' => ['fieldType' => 'color-picker'],
            ],
            [
                'id' => $descriptionId,
                'name' => 'foo_description',
                'type' => 'string',
                'config' => ['fieldType' => 'date-picker'],
            ],
        ];
        $this->repo->create($attributes, Context::createDefaultContext());
        $result = $this->repo->search(new Criteria([$sizeId]), Context::createDefaultContext())->getEntities();
        $attribute = $result->first();
        static::assertNotNull($attribute);

        static::assertEquals($sizeId, $attribute->getId());
        static::assertEquals($attributes[0]['name'], $attribute->getName());
        static::assertEquals($attributes[0]['type'], $attribute->getType());
        static::assertEquals($attributes[0]['config'], $attribute->getConfig());
    }

    public function testDelete(): void
    {
        $sizeId = Uuid::randomHex();
        $descriptionId = Uuid::randomHex();
        $attributes = [
            [
                'id' => $sizeId,
                'name' => 'foo_size',
                'type' => 'int',
            ],
            [
                'id' => $descriptionId,
                'name' => 'foo_description',
                'type' => 'string',
            ],
        ];
        $this->repo->create($attributes, Context::createDefaultContext());

        $result = $this->repo->delete([['id' => $sizeId]], Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);

        static::assertNotNull($event);
        static::assertCount(1, $event->getIds());
        static::assertEquals($sizeId, $event->getIds()[0]);
    }

    public function testUpdate(): void
    {
        $sizeId = Uuid::randomHex();
        $descriptionId = Uuid::randomHex();
        $attributes = [
            [
                'id' => $sizeId,
                'name' => 'foo_size',
                'type' => 'int',
            ],
            [
                'id' => $descriptionId,
                'name' => 'foo_description',
                'type' => 'string',
            ],
        ];
        $this->repo->create($attributes, Context::createDefaultContext());

        $update = [
            'id' => $descriptionId,
            'name' => 'updated_name',
        ];
        $result = $this->repo->update([$update], Context::createDefaultContext());

        $event = $result->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());
    }

    public function testUpsert(): void
    {
        $sizeId = Uuid::randomHex();
        $descriptionId = Uuid::randomHex();
        $attributes = [
            [
                'id' => $sizeId,
                'name' => 'foo_size',
                'type' => 'int',
                'label' => 'The size of foo products',
            ],
            [
                'id' => $descriptionId,
                'name' => 'foo_description',
                'type' => 'string',
                'label' => 'Foo description',
            ],
        ];
        $result = $this->repo->upsert($attributes, Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(2, $event->getPayloads());

        $result = $this->repo->upsert($attributes, Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(2, $event->getPayloads());
    }
}
