<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Services\EntityDeleteEventHelper;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\System\UsageData\Subscriber\DeletedEvent;
use Shopware\Tests\Unit\Core\System\UsageData\Subscriber\NonStorageAwareField;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(EntityDeleteEventHelper::class)]
class EntityDeletedEventHelperTest extends TestCase
{
    /**
     * @var EntityDefinition[]
     */
    private array $entityDefinitions = [];

    public function testGetPrimaryKeysFromEventForGivenEntityDefinition(): void
    {
        $idsCollection = new IdsCollection();
        $this->entityDefinitions = [new EntityWithSinglePrimaryKey()];

        $event = $this->createDeleteEvent([
            $idsCollection->get('first-product'),
            $idsCollection->get('second-product'),
        ]);

        $eventHelper = (new EntityDeleteEventHelper($event))
            ->forEntityDefinitions($this->entityDefinitions)
            ->prepare();
        $entityIds = $eventHelper->getEntityIds();
        static::assertArrayHasKey(EntityWithSinglePrimaryKey::ENTITY_NAME, $entityIds);
        static::assertEquals(
            [
                [
                    'id' => $idsCollection->get('first-product'),
                    'storageAwarePrimaryKey' => $idsCollection->get('first-product'),
                    'versionId' => Defaults::LIVE_VERSION,
                ],
                [
                    'id' => $idsCollection->get('second-product'),
                    'storageAwarePrimaryKey' => $idsCollection->get('second-product'),
                    'versionId' => Defaults::LIVE_VERSION,
                ],
            ],
            $entityIds[EntityWithSinglePrimaryKey::ENTITY_NAME],
        );
    }

    public function testPreparesOnlyForGivenEntityDefinitions(): void
    {
        $idsCollection = new IdsCollection();
        $this->entityDefinitions = [new EntityWithSinglePrimaryKey()];

        $event = $this->createDeleteEvent([
            $idsCollection->get('first-product'),
            $idsCollection->get('second-product'),
        ]);

        $eventHelper = (new EntityDeleteEventHelper($event))
            ->forEntityDefinitions([])
            ->prepare();
        static::assertEmpty($eventHelper->getEntityIds());
    }

    public function testItFiltersFields(): void
    {
        $idsCollection = new IdsCollection();
        $this->entityDefinitions = [new EntityWithSinglePrimaryKey()];

        $event = $this->createDeleteEvent([
            $idsCollection->get('first-product'),
            $idsCollection->get('second-product'),
        ]);

        $eventHelper = (new EntityDeleteEventHelper($event))
            ->forEntityDefinitions($this->entityDefinitions)
            ->withExcludedFields([StringField::class, VersionField::class])
            ->prepare();
        $entityIds = $eventHelper->getEntityIds();
        static::assertArrayHasKey(EntityWithSinglePrimaryKey::ENTITY_NAME, $entityIds);
        static::assertEquals(
            [
                [
                    'id' => $idsCollection->get('first-product'),
                ],
                [
                    'id' => $idsCollection->get('second-product'),
                ],
            ],
            $entityIds[EntityWithSinglePrimaryKey::ENTITY_NAME],
        );
    }

    /**
     * @param string[] $deleteIds
     */
    private function createDeleteEvent(array $deleteIds): EntityDeleteEvent
    {
        $registry = new StaticDefinitionInstanceRegistry(
            $this->entityDefinitions,
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $deleteCommands = [];
        foreach ($deleteIds as $deleteId) {
            $deleteCommands[] = $this->createDeleteCommand($definition, $deleteId);
        }

        return DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $deleteCommands
        );
    }

    private function createDeleteCommand(EntityDefinition $entityDefinition, string $id): DeleteCommand
    {
        return new DeleteCommand(
            $entityDefinition,
            [
                'id' => Uuid::fromHexToBytes($id),
                'non_storage_aware_primary_key' => 'this-will-be-ignored',
                'storage_aware_primary_key' => Uuid::fromHexToBytes($id),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            $this->createMock(EntityExistence::class)
        );
    }
}

/**
 * @internal
 */
class EntityWithSinglePrimaryKey extends EntityDefinition
{
    public const ENTITY_NAME = 'entity_with_single_primary_key';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new NonStorageAwareField('nonStorageAwarePrimaryKey'))->addFlags(new PrimaryKey()),
            (new StringField('storage_aware_primary_key', 'storageAwarePrimaryKey'))->addFlags(new PrimaryKey()),
            (new VersionField())->addFlags(),
            new NonStorageAwareField('nonStorageAware'),
            new ReferenceVersionField(ProductDefinition::class, 'product_version_id'),
        ]);
    }
}
