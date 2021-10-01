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
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\TestDefaults;

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

    public function testChangeSetWithMultipleCommandsForSameEntityType(): void
    {
        $productId1 = $this->createProduct();
        $productId2 = $this->createProduct();

        $updates = [
            ['id' => $productId1, 'stock' => 100],
            ['id' => $productId2, 'stock' => 50],
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

        $result = $this->productRepository->update($updates, Context::createDefaultContext());

        $changeSets = $this->getChangeSets(ProductDefinition::ENTITY_NAME, $result, 2);
        $changeSetForProduct1 = array_values(array_filter($changeSets, function (ChangeSet $changeSet) use (&$productId1) {
            return $changeSet->getBefore('id') === hex2bin($productId1);
        }))[0];
        $changeSetForProduct2 = array_values(array_filter($changeSets, function (ChangeSet $changeSet) use (&$productId2) {
            return $changeSet->getBefore('id') === hex2bin($productId2);
        }))[0];

        static::assertNotNull($changeSetForProduct1);
        static::assertTrue($changeSetForProduct1->hasChanged('stock'));
        static::assertEquals(1, $changeSetForProduct1->getBefore('stock'));
        static::assertEquals(100, $changeSetForProduct1->getAfter('stock'));

        static::assertNotNull($changeSetForProduct2);
        static::assertTrue($changeSetForProduct2->hasChanged('stock'));
        static::assertEquals(1, $changeSetForProduct2->getBefore('stock'));
        static::assertEquals(50, $changeSetForProduct2->getAfter('stock'));
    }

    public function testCustomFieldsMergeWithIntegers(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'customFields' => ['a' => 1234],
        ];

        $countryRepo = $this->getContainer()->get('country.repository');

        $countryRepo->upsert([$data], Context::createDefaultContext());

        $data['customFields']['b'] = 1;
        $data['customFields']['c'] = 1.56;
        $data['customFields']['d'] = true;
        $data['customFields']['e'] = ['a'];
        $data['customFields']['f'] = new \stdClass();
        $data['customFields']['g'] = 'test';

        $countryRepo->upsert([$data], Context::createDefaultContext());

        /** @var CountryEntity $country */
        $country = $countryRepo->search(new Criteria([$id]), Context::createDefaultContext())->first();

        $customFields = $country->getCustomFields();

        static::assertIsInt($customFields['a']);
        static::assertIsInt($customFields['b']);
        static::assertSame(1234, $customFields['a']);
        static::assertSame(1, $customFields['b']);

        static::assertIsFloat($customFields['c']);
        static::assertSame(1.56, $customFields['c']);

        static::assertIsBool($customFields['d']);
        static::assertTrue($customFields['d']);

        static::assertIsArray($customFields['e']);
        static::assertSame(['a'], $customFields['e']);

        static::assertIsArray($customFields['f']);
        static::assertEmpty($customFields['f']);
        static::assertSame($customFields['g'], 'test');
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
                ['id' => $id, 'salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
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

    private function getChangeSets(string $entity, EntityWrittenContainerEvent $result, int $expectedSize): array
    {
        $event = $result->getEventByEntityName($entity);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount($expectedSize, $event->getWriteResults());

        return array_map(function (EntityWriteResult $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            static::assertInstanceOf(ChangeSet::class, $changeSet);

            return $changeSet;
        }, $event->getWriteResults());
    }
}
