<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

class EntityWrittenEventSerializationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testEventCanBeSerialized(): void
    {
        $container = $this->writeTestProduct();
        $event = $container->getEventByEntityName(ProductDefinition::ENTITY_NAME);

        $encoded = json_encode($event);
        static::assertNotFalse($encoded);
        static::assertJson($encoded);

        $encoded = json_encode($container);
        static::assertNotFalse($encoded);
        static::assertJson($encoded);
    }

    /**
     * @depends testEventCanBeSerialized
     */
    public function testContainerEventCanBeDispatchedAsMessage(): void
    {
        $event = $this->writeTestProduct();
        /** @var MessageBusInterface $bus */
        $bus = $this->getContainer()->get('messenger.bus.shopware');

        $failed = false;

        try {
            $bus->dispatch($event);
        } catch (\Exception $e) {
            $failed = true;
        }
        static::assertFalse($failed);
    }

    private function writeTestProduct(): EntityWrittenContainerEvent
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        return $productRepository->create(
            [[
                'id' => Uuid::randomHex(),
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand',
                ],
                'name' => 'wusel',
                'productNumber' => Uuid::randomHex(),
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
                'stock' => 0,
            ]],
            Context::createDefaultContext()
        );
    }
}
