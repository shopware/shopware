<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\Api\Serializer\_fixtures\TestAttributeEntity;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(JsonApiEncoder::class)]
class JsonApiEncoderTest extends TestCase
{
    public function testEncodeWithAttributeEntity(): void
    {
        $entity = (new TestAttributeEntity())->assign([
            'id' => 'entity-id',
            '_uniqueIdentifier' => 'entity-id',
            'customerId' => 'customer-id',
        ]);
        $entity->products = [
            'product-id' => (new ProductEntity())->assign(['id' => 'product-id', '_uniqueIdentifier' => 'product-id']),
        ];
        $entity->customer = (new CustomerEntity())->assign(['id' => 'customer-id', '_uniqueIdentifier' => 'customer-id']);

        $definition = $this->getAttributeEntityDefinition();

        $encoder = new JsonApiEncoder();
        $result = $encoder->encode(new Criteria(), $definition, $entity, '/api');
        $result = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $result);
        static::assertArrayHasKey('relationships', $result['data']);
        static::assertCount(2, $result['data']['relationships']);

        foreach ($result['data']['relationships'] as $key => $relationship) {
            static::assertArrayHasKey('data', $relationship);
            static::assertIsArray($relationship['data']);

            if ($key === 'customer') {
                static::assertEquals([
                    'type' => 'customer',
                    'id' => 'customer-id',
                ], $relationship['data']);
            }

            if ($key === 'products') {
                static::assertCount(1, $relationship['data']);
                static::assertEquals([
                    'type' => 'product',
                    'id' => 'product-id',
                ], $relationship['data'][0]);
            }
        }
    }

    private function getAttributeEntityDefinition(): AttributeEntityDefinition
    {
        $meta = [
            'entity_name' => 'test_attribute_entity',
            'fields' => [
                [
                    'type' => 'uuid',
                    'name' => 'id',
                    'class' => IdField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['id', 'id'],
                ],
                [
                    'type' => 'fk',
                    'name' => 'customerId',
                    'class' => FkField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['customer_id', 'customerId', 'customer'],
                ],
                [
                    'type' => 'many-to-one',
                    'name' => 'customer',
                    'class' => ManyToOneAssociationField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['customer', 'customer_id', 'customer', 'id'],
                ],
                [
                    'type' => 'many-to-many',
                    'name' => 'products',
                    'class' => ManyToManyAssociationField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['products', 'product', 'test_attribute_entity_product', 'test_attribute_entity_id', 'product_id'],
                ],
            ],
        ];

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);

        $productDefinition = new ProductDefinition();
        $productDefinition->compile($definitionRegistry);
        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionRegistry);

        $container = new Container();
        $container->set(ProductDefinition::class, $productDefinition);
        $container->set(CustomerDefinition::class, $customerDefinition);
        $container->set(AttributeEntityDefinition::class, $this->createMock(AttributeEntityDefinition::class));

        $attributeEntityDefinition = new AttributeEntityDefinition($meta);
        $attributeEntityDefinition->compile(new DefinitionInstanceRegistry($container, [
            'customer' => CustomerDefinition::class,
            'product' => ProductDefinition::class,
            'test_attribute_entity_product' => AttributeEntityDefinition::class,
        ], []));

        return $attributeEntityDefinition;
    }
}
