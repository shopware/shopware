<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityWriteGatewayTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->productRepository = $this->getContainer()->get('product.repository');
    }

    public function testFetchChangeSet(): void
    {
        $id = $this->createProduct();

        $update = ['id' => $id, 'stock' => 100];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('stock'));
        static::assertEquals(1, $changeSet->getBefore('stock'));
        static::assertEquals(100, $changeSet->getAfter('stock'));
    }

    public function testUpdateWithSameValue(): void
    {
        $id = $this->createProduct();

        $update = ['id' => $id, 'stock' => 1];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductDefinition::ENTITY_NAME, $result);

        static::assertFalse($changeSet->hasChanged('stock'));
        static::assertEquals('1', $changeSet->getBefore('stock'));
        static::assertNull($changeSet->getAfter('stock'));
    }

    public function testChangeSetWithDeletes(): void
    {
        $id = $this->createProduct();

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->delete([['id' => $id]], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductDefinition::ENTITY_NAME, $result);
        static::assertTrue($changeSet->hasChanged('id'));
        static::assertTrue($changeSet->hasChanged('product_number'));
        static::assertTrue($changeSet->hasChanged('price'));

        static::assertNull($changeSet->getAfter('id'));
        static::assertNull($changeSet->getAfter('product_number'));
        static::assertNull($changeSet->getAfter('price'));

        $changeSet = $this->getChangeSet(ProductCategoryDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('product_id'));
        static::assertEquals(Uuid::fromHexToBytes($id), $changeSet->getBefore('product_id'));
        static::assertNull($changeSet->getAfter('product_id'));
    }

    public function testChangeSetWithTranslations(): void
    {
        $id = $this->createProduct();

        $update = ['id' => $id, 'name' => 'updated'];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductTranslationDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('name'));
        static::assertEquals('test', $changeSet->getBefore('name'));
        static::assertEquals('updated', $changeSet->getAfter('name'));
    }

    public function testChangeSetWithOneToMany(): void
    {
        $id = $this->createProduct();

        $update = [
            'id' => $id,
            'visibilities' => [
                ['id' => $id, 'visibility' => ProductVisibilityDefinition::VISIBILITY_LINK],
            ],
        ];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductVisibilityDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('visibility'));
        static::assertEquals(ProductVisibilityDefinition::VISIBILITY_ALL, $changeSet->getBefore('visibility'));
        static::assertEquals(ProductVisibilityDefinition::VISIBILITY_LINK, $changeSet->getAfter('visibility'));
    }

    public function testChangeSetWithManyToOne(): void
    {
        $id = $this->createProduct();
        $newId = Uuid::randomHex();

        $update = [
            'id' => $id,
            'manufacturer' => [
                'id' => $newId,
                'name' => 'new manufacturer',
            ],
        ];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('product_manufacturer_id'));
        static::assertEquals($id, Uuid::fromBytesToHex($changeSet->getBefore('product_manufacturer_id')));
        static::assertEquals($newId, Uuid::fromBytesToHex($changeSet->getAfter('product_manufacturer_id')));
    }

    private function createProduct(): string
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'test'],
            ],
            'visibilities' => [
                ['id' => $id, 'salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->productRepository->create([$data], Context::createDefaultContext());

        return $id;
    }

    private function getChangeSet(string $entity, EntityWrittenContainerEvent $result): ChangeSet
    {
        $event = $result->getEventByEntityName($entity);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(1, $event->getWriteResults());

        $changeSet = $event->getWriteResults()[0]->getChangeSet();
        static::assertInstanceOf(ChangeSet::class, $changeSet);

        return $changeSet;
    }
}
