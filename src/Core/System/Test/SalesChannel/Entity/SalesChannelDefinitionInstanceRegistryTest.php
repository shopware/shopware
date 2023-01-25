<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[Package('sales-channel')]
class SalesChannelDefinitionInstanceRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRegister(): void
    {
        $registry = new SalesChannelDefinitionInstanceRegistry(
            'sales_channel_definition.',
            new Container(),
            [],
            []
        );

        $registry->register(new ProductDefinition());

        static::assertInstanceOf(ProductDefinition::class, $registry->get(ProductDefinition::class));
        static::assertTrue($registry->has(ProductDefinition::ENTITY_NAME));
        static::assertInstanceOf(ProductDefinition::class, $registry->getByEntityName(ProductDefinition::ENTITY_NAME));
        static::assertInstanceOf(ProductDefinition::class, $registry->getByEntityClass(new ProductEntity()));
    }

    public function testItThrowsExceptionWhenSalesChannelRepositoryWasNotFoundByEntityName(): void
    {
        $registry = new SalesChannelDefinitionInstanceRegistry(
            'sales_channel_definition.',
            new Container(),
            [],
            []
        );

        $this->expectException(SalesChannelRepositoryNotFoundException::class);
        $registry->getSalesChannelRepository('fooBar');
    }
}
