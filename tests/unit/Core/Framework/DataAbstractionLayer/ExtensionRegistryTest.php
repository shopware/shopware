<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\ExtensionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
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
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $salesChannelDefinitionRegistry = $this->createMock(SalesChannelDefinitionInstanceRegistry::class);

        $definitionRegistry->expects(static::never())->method('getByEntityName');
        $definitionRegistry->expects(static::never())->method('get');
        $definitionRegistry->expects(static::never())->method('getByEntityName');
        $salesChannelDefinitionRegistry->expects(static::never())->method('getByEntityName');
        $salesChannelDefinitionRegistry->expects(static::never())->method('get');

        $registry = new ExtensionRegistry([], []);
        $registry->configureExtensions(
            $definitionRegistry,
            $salesChannelDefinitionRegistry
        );
    }

    public function testUnknownDefinitionIsIgnored(): void
    {
        $salesChannelDefinitionRegistry = $this->createMock(SalesChannelDefinitionInstanceRegistry::class);
        $definitionRegistry = $this->definitionRegistry([ProductDefinition::class]);

        $registry = new ExtensionRegistry([], [$this->getBulkExtension()]);
        $registry->configureExtensions(
            $definitionRegistry,
            $salesChannelDefinitionRegistry
        );

        static::assertCount(2, $definitionRegistry->get(ProductDefinition::class)->getFields()->filterByFlag(Extension::class));
        static::assertFalse($definitionRegistry->has(CategoryDefinition::class));
    }

    public function testAllDefinitionsFound(): void
    {
        $definitionRegistry = $this->definitionRegistry([ProductDefinition::class, CategoryDefinition::class]);
        $salesChannelDefinitionRegistry = $this->createMock(SalesChannelDefinitionInstanceRegistry::class);

        $registry = new ExtensionRegistry([], [$this->getBulkExtension()]);
        $registry->configureExtensions(
            $definitionRegistry,
            $salesChannelDefinitionRegistry
        );

        static::assertCount(2, $definitionRegistry->get(ProductDefinition::class)->getFields()->filterByFlag(Extension::class));
        static::assertCount(2, $definitionRegistry->get(CategoryDefinition::class)->getFields()->filterByFlag(Extension::class));
    }

    public function testAttributeDefinition(): void
    {
        $definitionRegistry = $this->definitionRegistry([
            'product.definition' => new AttributeEntityDefinition(['entity_name' => 'product', 'fields' => []]),
        ]);
        $salesChannelDefinitionRegistry = $this->createMock(SalesChannelDefinitionInstanceRegistry::class);

        $registry = new ExtensionRegistry([], [$this->getBulkExtension()]);
        $registry->configureExtensions(
            $definitionRegistry,
            $salesChannelDefinitionRegistry
        );

        static::assertCount(2, $definitionRegistry->getByEntityName('product')->getFields()->filterByFlag(Extension::class));
    }

    /**
     * @param array<int|string, class-string<EntityDefinition>|EntityDefinition> $definitions
     */
    private function definitionRegistry(array $definitions): StaticDefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            $definitions,
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGateway::class)
        );
    }

    private function getBulkExtension(): BulkEntityExtension
    {
        return new class extends BulkEntityExtension {
            public function collect(): \Generator
            {
                yield 'product' => [
                    new FkField('main_category_id', 'mainCategoryId', CategoryDefinition::class),
                    new OneToOneAssociationField('category', 'main_category_id', 'id', CategoryDefinition::class),
                ];

                yield 'category' => [
                    new FkField('product_id', 'productId', ProductDefinition::class),
                    new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class),
                ];
            }
        };
    }
}
