<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Attribute;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Attribute\AttributeDefinition;
use Shopware\Core\Framework\Attribute\AttributeEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class AttributeRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreate(): void
    {
        $repo = $this->getContainer()->get('attribute.repository');

        $id = Uuid::uuid4()->getHex();
        $attribute = [
            'id' => $id,
            'name' => 'foo.size',
            'type' => 'int',
        ];
        $result = $repo->create([$attribute], Context::createDefaultContext());

        $events = $result->getEventByDefinition(AttributeDefinition::class);
        static::assertNotNull($events);

        $payloads = $events->getPayloads();
        static::assertNotEmpty($payloads);

        static::assertEquals($attribute['id'], $payloads[0]['id']);
        static::assertEquals($attribute['name'], $payloads[0]['name']);
        static::assertEquals($attribute['type'], $payloads[0]['type']);
    }

    public function testSearchId(): void
    {
        $repo = $this->getContainer()->get('attribute.repository');

        $sizeId = Uuid::uuid4()->getHex();
        $descriptionId = Uuid::uuid4()->getHex();
        $attributes = [
            [
                'id' => $sizeId,
                'name' => 'foo.size',
                'type' => 'int',
                'config' => ['fieldType' => 'color-picker'],
            ],
            [
                'id' => $descriptionId,
                'name' => 'foo.description',
                'type' => 'string',
                'config' => ['fieldType' => 'date-picker'],
            ],
        ];
        $repo->create($attributes, Context::createDefaultContext());
        $result = $repo->search(new Criteria([$sizeId]), Context::createDefaultContext());

        /** @var AttributeEntity $attribute */
        $attribute = $result->first();
        static::assertNotNull($attribute);

        static::assertEquals($sizeId, $attribute->getId());
        static::assertEquals($attributes[0]['name'], $attribute->getName());
        static::assertEquals($attributes[0]['type'], $attribute->getType());
        static::assertEquals($attributes[0]['config'], $attribute->getConfig());
    }

    public function testDelete(): void
    {
        $repo = $this->getContainer()->get('attribute.repository');

        $sizeId = Uuid::uuid4()->getHex();
        $descriptionId = Uuid::uuid4()->getHex();
        $attributes = [
            [
                'id' => $sizeId,
                'name' => 'foo.size',
                'type' => 'int',
            ],
            [
                'id' => $descriptionId,
                'name' => 'foo.description',
                'type' => 'string',
            ],
        ];
        $repo->create($attributes, Context::createDefaultContext());

        $result = $repo->delete([['id' => $sizeId]], Context::createDefaultContext());
        $event = $result->getEventByDefinition(AttributeDefinition::class);

        static::assertCount(1, $event->getIds());
        static::assertEquals($sizeId, $event->getIds()[0]);
    }

    public function testUpdate(): void
    {
        $repo = $this->getContainer()->get('attribute.repository');

        $sizeId = Uuid::uuid4()->getHex();
        $descriptionId = Uuid::uuid4()->getHex();
        $attributes = [
            [
                'id' => $sizeId,
                'name' => 'foo.size',
                'type' => 'int',
            ],
            [
                'id' => $descriptionId,
                'name' => 'foo.description',
                'type' => 'string',
            ],
        ];
        $repo->create($attributes, Context::createDefaultContext());

        $update = [
            'id' => $descriptionId,
            'name' => 'Updated name',
        ];
        $result = $repo->update([$update], Context::createDefaultContext());

        $event = $result->getEventByDefinition(AttributeDefinition::class);
        static::assertCount(1, $event->getPayloads());
    }

    public function testUpsert(): void
    {
        $repo = $this->getContainer()->get('attribute.repository');

        $sizeId = Uuid::uuid4()->getHex();
        $descriptionId = Uuid::uuid4()->getHex();
        $attributes = [
            [
                'id' => $sizeId,
                'name' => 'foo.size',
                'type' => 'int',
                'label' => 'The size of foo products',
            ],
            [
                'id' => $descriptionId,
                'name' => 'foo.description',
                'type' => 'string',
                'label' => 'Foo description',
            ],
        ];
        $result = $repo->upsert($attributes, Context::createDefaultContext());
        $event = $result->getEventByDefinition(AttributeDefinition::class);
        static::assertCount(2, $event->getPayloads());

        $result = $repo->upsert($attributes, Context::createDefaultContext());
        $event = $result->getEventByDefinition(AttributeDefinition::class);
        static::assertCount(2, $event->getPayloads());
    }
}
