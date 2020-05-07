<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\Subscriber\ProductLoadedSubscriber;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use function Flag\skipTestNext7399;

class ProductLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        skipTestNext7399($this);
    }

    public function testExtensionSubscribesToProductLoaded(): void
    {
        static::assertArrayHasKey(ProductEvents::PRODUCT_LOADED_EVENT, ProductLoadedSubscriber::getSubscribedEvents());
        static::assertCount(1, ProductLoadedSubscriber::getSubscribedEvents()[ProductEvents::PRODUCT_LOADED_EVENT]);
    }

    public function testItAddsVariantCharacteristics(): void
    {
        $subscriber = $this->getContainer()->get(ProductLoadedSubscriber::class);
        $context = Context::createDefaultContext();

        $propertyNames = [
            'red',
            'XL',
            'slim fit',
        ];

        $productEntity = $this->createVariant($propertyNames);

        $productLoadedEvent = new EntityLoadedEvent($this->getContainer()->get(ProductDefinition::class), [$productEntity], $context);
        $subscriber->addVariantCharacteristics($productLoadedEvent);

        static::assertEquals(
            implode(' - ', $propertyNames),
            $productEntity->getVariantCharacteristics()
        );
    }

    public function testItAddsVariantCharacteristicsWidthGroupConfig(): void
    {
        $subscriber = $this->getContainer()->get(ProductLoadedSubscriber::class);
        $context = Context::createDefaultContext();

        $propertyNames = [
            'red',
            'XL',
            'slim fit',
        ];

        $productEntity = $this->createVariantWithGroupConfig($propertyNames);

        $productLoadedEvent = new EntityLoadedEvent($this->getContainer()->get(ProductDefinition::class), [$productEntity], $context);
        $subscriber->addVariantCharacteristics($productLoadedEvent);

        static::assertEquals(
            implode(' - ', $propertyNames),
            $productEntity->getVariantCharacteristics()
        );
    }

    private function createVariant(array $propertyNames = [])
    {
        $productEntity = new ProductEntity();
        $productEntity->setId(Uuid::randomHex());
        $productEntity->setOptions(new PropertyGroupOptionCollection());

        foreach ($propertyNames as $name) {
            $groupId = Uuid::randomHex();

            $configGroupEntity = new PropertyGroupOptionEntity();
            $configGroupEntity->setUniqueIdentifier($groupId);
            $configGroupEntity->setName($name);
            $configGroupEntity->setGroupId($groupId);
            $productEntity->getOptions()->add($configGroupEntity);
        }

        return $productEntity;
    }

    private function createVariantWithGroupConfig(array $propertyNames = [])
    {
        $productEntity = new ProductEntity();
        $productEntity->setId(Uuid::randomHex());
        $productEntity->setOptions(new PropertyGroupOptionCollection());

        $configuratorGroupConfig = [];

        foreach ($propertyNames as $name) {
            $groupId = Uuid::randomHex();

            $configuratorGroupConfig[] = [
                'id' => $groupId,
                'representation' => 'box',
                'expressionForListings' => true,
            ];

            $configGroupEntity = new PropertyGroupOptionEntity();
            $configGroupEntity->setUniqueIdentifier($groupId);
            $configGroupEntity->setName($name);
            $configGroupEntity->setGroupId($groupId);
            $productEntity->getOptions()->add($configGroupEntity);
        }

        $productEntity->setConfiguratorGroupConfig($configuratorGroupConfig);

        return $productEntity;
    }
}
