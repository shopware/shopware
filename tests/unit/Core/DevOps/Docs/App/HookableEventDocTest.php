<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\App\HookableEventDoc;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EntityType;

/**
 * @internal
 */
#[CoversClass(HookableEventDoc::class)]
class HookableEventDocTest extends TestCase
{
    /**
     * @param list<string> $permissions
     * @param array<string, string> $expectedPayload
     */
    #[DataProvider('entityWrittenProvider')]
    public function testFromEntityWrittenEvent(
        string $event,
        array $permissions,
        string $expectedDesc,
        string $expectedPerm,
        array $expectedPayload
    ): void {
        $doc = HookableEventDoc::fromEntityWrittenEvent($event, $permissions);
        static::assertSame($event, $doc->getEventName());
        static::assertSame($expectedDesc, $doc->getDescription());
        static::assertSame($expectedPerm, $doc->getPermissions());
        static::assertSame(json_encode($expectedPayload, \JSON_THROW_ON_ERROR), $doc->getPayload());
    }

    /**
     * @return iterable<string, array{
     *     string,
     *     list<string>,
     *     string,
     *     string,
     *     array<string, string>
     * }>
     */
    public static function entityWrittenProvider(): iterable
    {
        yield 'with permissions' => [
            'product.written',
            ['a', 'b'],
            'Triggers when a product is written',
            '`a` `b`',
            [
                'entity' => 'product',
                'operation' => EntityWriteResult::OPERATION_UPDATE . ' ' . EntityWriteResult::OPERATION_INSERT,
                'primaryKey' => 'array string',
                'payload' => 'array',
            ],
        ];
        yield 'without permissions' => [
            'foo.deleted',
            [],
            'Triggers when a foo is deleted',
            '-',
            [
                'entity' => 'foo',
                'operation' => 'deleted',
                'primaryKey' => 'array string',
                'payload' => 'array',
            ],
        ];
    }

    public function testFromEntityWrittenEventJsonException(): void
    {
        // Use an invalid UTF-8 string as the entity name to trigger json_encode failure
        $invalid = "\xB1\x31";
        $this->expectExceptionObject(new \RuntimeException('Can not parsing payload for written event'));
        HookableEventDoc::fromEntityWrittenEvent($invalid . '.written', []);
    }

    public function testFromBusinessEvent(): void
    {
        $event = new BusinessEventDefinition(
            name: 'event.name',
            data: [
                'foo' => ['type' => 'string'],
                'bar' => ['type' => EntityType::TYPE, 'entityClass' => DummyEntityDefinition::class],
                'baz' => ['type' => EntityCollectionType::TYPE, 'entityClass' => DummyEntityDefinition::class],
            ],
            class: '' // class parameter required
        );
        $doc = HookableEventDoc::fromBusinessEvent($event, ['perm'], 'desc');
        static::assertSame('event.name', $doc->getEventName());
        static::assertSame('desc', $doc->getDescription());
        static::assertSame('`perm`', $doc->getPermissions());
        static::assertSame(json_encode([
            'foo' => 'string',
            EntityType::TYPE => 'dummy',
        ], \JSON_THROW_ON_ERROR), $doc->getPayload());
    }

    public function testFromBusinessEventJsonException(): void
    {
        // Use an invalid UTF-8 string as a value in the event data to trigger json_encode failure
        $invalid = "\xB1\x31";
        $event = new BusinessEventDefinition(
            name: 'event.name',
            data: [
                'foo' => ['type' => $invalid],
            ],
            class: ''
        );
        $this->expectExceptionObject(new \RuntimeException('Can not parsing payload for business event'));
        HookableEventDoc::fromBusinessEvent($event, [], 'desc');
    }

    // Proxy for testing private static method parsingSimpleBusinessEventPayload
    /**
     * @param array<string, mixed> $dataTypes
     *
     * @return array<string, string>
     */
    public static function callParsingSimpleBusinessEventPayload(array $dataTypes): array
    {
        return \Closure::bind(static fn ($dataTypes) => HookableEventDoc::parsingSimpleBusinessEventPayload($dataTypes), null, HookableEventDoc::class)($dataTypes);
    }

    public function testParsingSimpleBusinessEventPayloadEmpty(): void
    {
        $result = self::callParsingSimpleBusinessEventPayload([]);
        static::assertSame([], $result);
    }

    public function testFromBusinessEventWithEmptyDescription(): void
    {
        $event = new BusinessEventDefinition(
            name: 'event.name',
            data: [
                'foo' => ['type' => 'string'],
            ],
            class: ''
        );
        $doc = HookableEventDoc::fromBusinessEvent($event, [], '');
        static::assertSame('', $doc->getDescription());
    }

    public function testConstructorWithNulls(): void
    {
        $doc = new HookableEventDoc('event', null, '', null);
        static::assertNull($doc->getDescription());
        static::assertNull($doc->getPayload());
    }

    public function testParsingSimpleBusinessEventPayloadEntityTypeAndCollectionType(): void
    {
        $dataTypes = [
            'foo' => ['type' => EntityType::TYPE, 'entityClass' => DummyEntityDefinition::class],
            'bar' => ['type' => EntityCollectionType::TYPE, 'entityClass' => DummyEntityDefinition::class],
        ];
        $result = self::callParsingSimpleBusinessEventPayload($dataTypes);
        // Only the last wins, so EntityType::TYPE => 'dummy'
        static::assertSame(['entity' => 'dummy'], ['entity' => $result[EntityType::TYPE]]);
    }
}

/**
 * @internal
 */
class DummyEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'dummy';
    }

    /**
     * @return class-string<Entity>
     */
    public function getEntityClass(): string
    {
        // Return the FQCN of a class extending Entity
        return Entity::class;
    }

    /**
     * @return class-string
     */
    public function getCollectionClass(): string
    {
        return DummyEntityDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([]);
    }
}
