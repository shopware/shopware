<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\EventWrapper;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventWrapper\EventWrapper;
use Shopware\Core\Framework\Webhook\EventWrapper\HookableEntityWrittenEvent;
use function Flag\skipTestNext10286;

class EventWrapperTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function setUp(): void
    {
        skipTestNext10286($this);
    }

    public function testSubscribedEvents(): void
    {
        static::assertEquals(
            [EntityWrittenContainerEvent::class => 'wrapEntityWrittenEvent'],
            EventWrapper::getSubscribedEvents()
        );
    }

    public function testWrapsEntityWriteForHookableEntityInsert(): void
    {
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $id = Uuid::randomHex();
        $listenerCalled = false;
        $insertListener = function (HookableEntityWrittenEvent $event) use (&$listenerCalled, $id): void {
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
            sort($actualUpdatedFields);
            sort($expectedUpdatedFields);

            static::assertEquals($expectedUpdatedFields, $actualUpdatedFields);

            $listenerCalled = true;
        };
        $eventDispatcher->addListener(
            HookableEntityWrittenEvent::class,
            $insertListener
        );

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->insertProduct($id, $productRepository);

        static::assertTrue($listenerCalled);

        $eventDispatcher->removeListener(HookableEntityWrittenEvent::class, $insertListener);
    }

    public function testWrapsEntityWriteForHookableEntityUpdate(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->insertProduct($id, $productRepository);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $listenerCalled = false;
        $updateListener = function (HookableEntityWrittenEvent $event) use (&$listenerCalled, $id): void {
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

            $listenerCalled = true;
        };
        $eventDispatcher->addListener(
            HookableEntityWrittenEvent::class,
            $updateListener
        );

        $productRepository->upsert([
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

        static::assertTrue($listenerCalled);

        $eventDispatcher->removeListener(HookableEntityWrittenEvent::class, $updateListener);
    }

    public function testWrapsEntityWriteForHookableEntityDelete(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->insertProduct($id, $productRepository);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $listenerCalled = false;
        $deleteListener = function (HookableEntityWrittenEvent $event) use (&$listenerCalled, $id): void {
            static::assertEquals('product.deleted', $event->getName());
            static::assertEquals([[
                'entity' => 'product',
                'operation' => 'delete',
                'primaryKey' => $id,
            ]], $event->getWebhookPayload());

            $listenerCalled = true;
        };
        $eventDispatcher->addListener(
            HookableEntityWrittenEvent::class,
            $deleteListener
        );

        $productRepository->delete([['id' => $id]], Context::createDefaultContext());

        static::assertTrue($listenerCalled);

        $eventDispatcher->removeListener(HookableEntityWrittenEvent::class, $deleteListener);
    }

    public function testDoesntWrapEntityWriteForNotHookableEntity(): void
    {
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $listenerCalled = false;
        $eventListener = function () use (&$listenerCalled): void {
            $listenerCalled = true;
        };
        $eventDispatcher->addListener(
            HookableEntityWrittenEvent::class,
            $eventListener
        );

        $id = Uuid::randomHex();
        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        $taxRepository->upsert([
            [
                'id' => $id,
                'name' => 'luxury',
                'taxRate' => '25',
            ],
        ], Context::createDefaultContext());

        $taxRepository->upsert([
            [
                'id' => $id,
                'name' => 'test update',
            ],
        ], Context::createDefaultContext());

        $taxRepository->delete([['id' => $id]], Context::createDefaultContext());

        static::assertFalse($listenerCalled);

        $eventDispatcher->removeListener(HookableEntityWrittenEvent::class, $eventListener);
    }

    public function testWrapsEntityWriteForTranslationUpdate(): void
    {
        $id = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->insertProduct($id, $productRepository);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $listenerCalled = false;
        $updateListener = function (HookableEntityWrittenEvent $event) use (&$listenerCalled, $id): void {
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

            $listenerCalled = true;
        };
        $eventDispatcher->addListener(
            HookableEntityWrittenEvent::class,
            $updateListener
        );

        $productRepository->upsert([
            [
                'id' => $id,
                'name' => 'a new name',
                'description' => 'a fancy description.',
            ],
        ], Context::createDefaultContext());

        static::assertTrue($listenerCalled);

        $eventDispatcher->removeListener(HookableEntityWrittenEvent::class, $updateListener);
    }

    private function insertProduct(string $id, EntityRepositoryInterface $productRepository): void
    {
        $productRepository->upsert([
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
