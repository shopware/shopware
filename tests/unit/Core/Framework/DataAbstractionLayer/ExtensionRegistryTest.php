<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\ExtensionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(ExtensionRegistry::class)]
class ExtensionRegistryTest extends TestCase
{
    public function testNoBulkRegistered(): void
    {
        $registry = new ExtensionRegistry([], []);

        $bulks = $registry->buildBulkExtensions(
            $this->registry([])
        );

        static::assertEmpty($bulks);
    }

    public function testUnkownDefinition(): void
    {
        $registry = new ExtensionRegistry([], [new MyBulkExtension()]);

        $bulks = $registry->buildBulkExtensions(
            $this->registry([ProductDefinition::class])
        );

        static::assertCount(1, $bulks);
    }

    public function testAllDefinitionsFound(): void
    {
        $registry = new ExtensionRegistry([], [new MyBulkExtension()]);

        $bulks = $registry->buildBulkExtensions(
            $this->registry([ProductDefinition::class, CategoryDefinition::class])
        );

        static::assertCount(2, $bulks);
    }

    public function testAttributeDefinition(): void
    {
        $registry = new ExtensionRegistry([], [new MyBulkExtension()]);

        $bulks = $registry->buildBulkExtensions(
            $this->registry([
                'product.definition' => new AttributeEntityDefinition(['entity_name' => 'product'])
            ])
        );

        static::assertCount(1, $bulks);
    }

    /**
     * @param array<int|string, class-string<EntityDefinition>|EntityDefinition> $definitions
     */
    private function registry(array $definitions): StaticDefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            $definitions,
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGateway::class)
        );
    }
}

class MyBulkExtension extends BulkEntityExtension
{
    public function collect(): \Generator
    {
        yield 'product' => [
            new FkField('main_category_id', 'mainCategoryId', CategoryDefinition::class),
        ];

        yield 'category' => [
            new FkField('product_id', 'productId', ProductDefinition::class),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class),
        ];
    }
}
