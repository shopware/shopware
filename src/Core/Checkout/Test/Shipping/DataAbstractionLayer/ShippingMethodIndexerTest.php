<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\DataAbstractionLayer\ShippingMethodIndexer;
use Shopware\Core\Checkout\Shipping\DataAbstractionLayer\ShippingMethodIndexingMessage;
use Shopware\Core\Checkout\Shipping\DataAbstractionLayer\ShippingMethodPriceDeprecationUpdater;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;

class ShippingMethodIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function tearDown(): void
    {
        $this->setBlueGreen(true);
    }

    public function testIterate(): void
    {
        $this->setBlueGreen(false);

        /** @var EntityWriter $entityWriter */
        $entityWriter = $this->getContainer()->get(EntityWriter::class);
        /** @var EntityDefinition $definition */
        $definition = $this->getContainer()->get(ShippingMethodDefinition::class);
        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());

        $newPriceId = Uuid::randomHex();
        $oldPriceId = Uuid::randomHex();

        $shippingMethodNew = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'active' => true,
            'availabilityRuleId' => $this->getAvailableShippingMethod()->getAvailabilityRuleId(),
            'deliveryTime' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
                'min' => 1,
                'max' => 3,
                'unit' => 'days',
            ],
            'prices' => [
                [
                    'id' => $newPriceId,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 12.37,
                            'gross' => 13.37,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
        ];
        $shippingMethodOld = $shippingMethodNew;
        $shippingMethodOld['id'] = Uuid::randomHex();
        $shippingMethodOld['prices'][0] = [
            'id' => $oldPriceId,
            'price' => 2.11,
            'currencyId' => Defaults::CURRENCY,
        ];

        $entityWriter->upsert($definition, [$shippingMethodNew, $shippingMethodOld], $writeContext);

        /** @var ShippingMethodIndexer $indexer */
        $indexer = $this->getContainer()->get(ShippingMethodIndexer::class);
        $message = $indexer->iterate(['offset' => 0]);

        static::assertInstanceOf(ShippingMethodIndexingMessage::class, $message);

        $data = $message->getData();
        static::assertContains($shippingMethodOld['id'], $data);
        static::assertContains($shippingMethodNew['id'], $data);

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('shipping_method_price.repository');

        $indexer->handle($message);

        /** @var ShippingMethodPriceEntity $actualNew */
        $actualNew = $repository->search(new Criteria([$newPriceId]), Context::createDefaultContext())->first();
        static::assertNotNull($actualNew);
        $actualNew = $actualNew->jsonSerialize();

        static::assertNotNull($actualNew['currencyPrice']);
        static::assertNotNull($actualNew['price']);
        static::assertNotNull($actualNew['currencyId']);

        $actualOld = $repository->search(new Criteria([$oldPriceId]), Context::createDefaultContext())->first();
        static::assertNotNull($actualOld);
        $actualOld = $actualOld->jsonSerialize();

        static::assertNotNull($actualOld['currencyPrice']);
        static::assertNotNull($actualOld['price']);
        static::assertNotNull($actualOld['currencyId']);
    }

    public function testDeprecationIndexerIsNotCalledIfBlueGreen(): void
    {
        $updater = $this->createMock(ShippingMethodPriceDeprecationUpdater::class);
        $indexer = new ShippingMethodIndexer(
            $updater,
            $this->getContainer()->get(IteratorFactory::class),
            $this->getContainer()->get(CacheClearer::class),
            $this->getContainer()->get('shipping_method.repository'),
            true
        );

        $updater->expects(static::never())->method('updateByEvent');
        $updater->expects(static::never())->method('updateByShippingMethodId');

        $message = $indexer->iterate(['offset' => 0]);
        $indexer->handle($message);
        $context = Context::createDefaultContext();

        $writtenEvent = new EntityWrittenEvent('shipping_method_price', ['id' => Uuid::randomHex()], $context);
        $event = new EntityWrittenContainerEvent($context, new NestedEventCollection([$writtenEvent]), []);
        $indexer->update($event);
    }

    private function setBlueGreen(?bool $enabled): void
    {
        $this->getContainer()->get(Connection::class)->rollBack();

        if ($enabled === null) {
            unset($_ENV['BLUE_GREEN_DEPLOYMENT']);
        } else {
            $_ENV['BLUE_GREEN_DEPLOYMENT'] = $enabled ? '1' : '0';
        }

        // reload env
        KernelLifecycleManager::bootKernel();

        $this->getContainer()->get(Connection::class)->beginTransaction();
        if ($enabled !== null) {
            $this->getContainer()->get(Connection::class)->executeUpdate('SET @TRIGGER_DISABLED = ' . ($enabled ? '0' : '1'));
        }
    }
}
