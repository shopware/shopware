<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\Hookable;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Test\Event\TestBusinessEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Hookable\HookableBusinessEvent;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;

class HookableEventFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var HookableEventFactory
     */
    private $hookableEventFactory;

    public function setUp(): void
    {
        $this->hookableEventFactory = $this->getContainer()->get(HookableEventFactory::class);
    }

    public function testDoesNotCreateEventForConcreteBusinessEvent(): void
    {
        $hookables = $this->hookableEventFactory->createHookablesFor(
            new BusinessEvent(
                'test',
                new TestBusinessEvent(Context::createDefaultContext())
            )
        );

        static::assertEmpty($hookables);
    }

    public function testDoesCreateHookableBusinessEvent(): void
    {
        $hookables = $this->hookableEventFactory->createHookablesFor(
            new TestBusinessEvent(Context::createDefaultContext())
        );

        static::assertCount(1, $hookables);
        static::assertInstanceOf(HookableBusinessEvent::class, $hookables[0]);
    }

    public function testCreatesHookableEntityInsert(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $writtenEvent = $this->insertProduct($id, $productRepository);

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);
        $event = $hookables[0];
        static::assertEquals('product.written', $event->getName());

        $payload = $event->getWebhookPayload();
        $actualUpdatedFields = $payload[0]['updatedFields'];
        unset($payload[0]['updatedFields']);

        static::assertEquals([[
            'entity' => 'product',
            'operation' => 'insert',
            'primaryKey' => $id,
        ]], $payload);

        $expectedUpdatedFields = [
            'versionId',
            'id',
            'parentVersionId',
            'manufacturerId',
            'productManufacturerVersionId',
            'taxId',
            'stock',
            'price',
            'productNumber',
            'isCloseout',
            'purchaseSteps',
            'minPurchase',
            'shippingFree',
            'restockTime',
            'createdAt',
            'name',
        ];

        foreach ($expectedUpdatedFields as $field) {
            static::assertContains($field, $actualUpdatedFields);
        }
    }

    public function testCreatesHookableEntityUpdate(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->insertProduct($id, $productRepository);

        $writtenEvent = $productRepository->upsert([
            [
                'id' => $id,
                'stock' => 99,
                'price' => [
                    [
                        'gross' => 200,
                        'net' => 250,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);
        $event = $hookables[0];
        static::assertEquals('product.written', $event->getName());

        $payload = $event->getWebhookPayload();
        $actualUpdatedFields = $payload[0]['updatedFields'];
        unset($payload[0]['updatedFields']);

        static::assertEquals([[
            'entity' => 'product',
            'operation' => 'update',
            'primaryKey' => $id,
        ]], $payload);

        $expectedUpdatedFields = [
            'stock',
            'price',
            'updatedAt',
            'id',
            'versionId',
        ];
        sort($actualUpdatedFields);
        sort($expectedUpdatedFields);

        static::assertEquals($expectedUpdatedFields, $actualUpdatedFields);
    }

    public function testCreatesHookableEntityDelete(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->insertProduct($id, $productRepository);

        $writtenEvent = $productRepository->delete([['id' => $id]], Context::createDefaultContext());

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);
        $event = $hookables[0];
        static::assertEquals('product.deleted', $event->getName());
        static::assertEquals([[
            'entity' => 'product',
            'operation' => 'delete',
            'primaryKey' => $id,
        ]], $event->getWebhookPayload());
    }

    public function testDoesNotCreateHookableNotHookableEntity(): void
    {
        $id = Uuid::randomHex();
        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        $createdEvent = $taxRepository->upsert([
            [
                'id' => $id,
                'name' => 'luxury',
                'taxRate' => '25',
            ],
        ], Context::createDefaultContext());

        static::assertEmpty(
            $this->hookableEventFactory->createHookablesFor($createdEvent)
        );

        $updatedEvent = $taxRepository->upsert([
            [
                'id' => $id,
                'name' => 'test update',
            ],
        ], Context::createDefaultContext());

        static::assertEmpty(
            $this->hookableEventFactory->createHookablesFor($updatedEvent)
        );

        $deletedEvent = $taxRepository->delete([['id' => $id]], Context::createDefaultContext());

        static::assertEmpty(
            $this->hookableEventFactory->createHookablesFor($deletedEvent)
        );
    }

    public function testCreatesEntityWriteForTranslationUpdate(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->insertProduct($id, $productRepository);

        $writtenEvent = $productRepository->upsert([
            [
                'id' => $id,
                'name' => 'a new name',
                'description' => 'a fancy description.',
            ],
        ], Context::createDefaultContext());

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);
        $event = $hookables[0];
        static::assertEquals('product.written', $event->getName());
        static::assertEquals([[
            'entity' => 'product',
            'operation' => 'update',
            'primaryKey' => $id,
            'updatedFields' => [
                'updatedAt',
                'id',
                'versionId',
                'name',
                'description',
            ],
        ]], $event->getWebhookPayload());
    }

    public function testCreatesMultipleHookables(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->insertProduct($id, $productRepository);

        $ruleRepository = $this->getContainer()->get('rule.repository');
        $ruleId = $ruleRepository->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        $writtenEvent = $productRepository->upsert([
            [
                'id' => $id,
                'name' => 'a new name',
                'description' => 'a fancy description.',
                'prices' => [
                    [
                        'id' => $id,
                        'ruleId' => $ruleId,
                        'quantityStart' => 1,
                        'price' => [
                            [
                                'gross' => 100,
                                'net' => 200,
                                'linked' => false,
                                'currencyId' => Defaults::CURRENCY,
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(2, $hookables);
        $event = $hookables[0];
        static::assertEquals('product.written', $event->getName());
        static::assertEquals([[
            'entity' => 'product',
            'operation' => 'update',
            'primaryKey' => $id,
            'updatedFields' => [
                'updatedAt',
                'id',
                'versionId',
                'name',
                'description',
            ],
        ]], $event->getWebhookPayload());

        $event = $hookables[1];
        static::assertEquals('product_price.written', $event->getName());
        static::assertEquals([[
            'entity' => 'product_price',
            'operation' => 'insert',
            'primaryKey' => $id,
            'updatedFields' => [
                'versionId',
                'id',
                'productId',
                'productVersionId',
                'ruleId',
                'price',
                'quantityStart',
                'createdAt',
            ],
        ]], $event->getWebhookPayload());
    }

    public function testDoesNotCreateMultipleHookablesForEmptyEvents(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->insertProduct($id, $productRepository);

        $ruleRepository = $this->getContainer()->get('rule.repository');
        $ruleId = $ruleRepository->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        /** @var EntityRepositoryInterface $productPriceRepository */
        $productPriceRepository = $this->getContainer()->get('product_price.repository');
        $writtenEvent = $productPriceRepository->upsert([
            [
                'id' => $id,
                'productId' => $id,
                'ruleId' => $ruleId,
                'quantityStart' => 1,
                'price' => [
                    [
                        'gross' => 100,
                        'net' => 200,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $hookables = $this->hookableEventFactory->createHookablesFor($writtenEvent);

        static::assertCount(1, $hookables);

        $event = $hookables[0];
        static::assertEquals('product_price.written', $event->getName());
        static::assertEquals([[
            'entity' => 'product_price',
            'operation' => 'insert',
            'primaryKey' => $id,
            'updatedFields' => [
                'versionId',
                'id',
                'productId',
                'productVersionId',
                'ruleId',
                'price',
                'quantityStart',
                'createdAt',
            ],
        ]], $event->getWebhookPayload());
    }

    private function insertProduct(string $id, EntityRepositoryInterface $productRepository): EntityWrittenContainerEvent
    {
        return $productRepository->upsert([
            [
                'id' => $id,
                'name' => 'testProduct',
                'productNumber' => 'SWC-1000',
                'stock' => 100,
                'manufacturer' => [
                    'name' => 'app creator',
                ],
                'price' => [
                    [
                        'gross' => 100,
                        'net' => 200,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
                'tax' => [
                    'name' => 'luxury',
                    'taxRate' => '25',
                ],
            ],
        ], Context::createDefaultContext());
    }
}
