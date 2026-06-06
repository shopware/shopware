<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportDefinition;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ProductExportHydrator;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ProductExportHydrator::class)]
class ProductExportHydratorTest extends TestCase
{
    private ProductExportHydrator $hydrator;

    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $container = new ContainerBuilder();
        $this->hydrator = new ProductExportHydrator($container);

        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [
                ProductExportDefinition::class,
                ProductStreamDefinition::class,
                SalesChannelDefinition::class,
                SalesChannelDomainDefinition::class,
                SalesChannelTypeDefinition::class,
                CurrencyDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $container->set(ProductExportHydrator::class, $this->hydrator);
    }

    public function testHydrationAssignsProviderField(): void
    {
        $definition = $this->definitionInstanceRegistry->get(ProductExportDefinition::class);

        $id = Uuid::randomBytes();
        $productStreamId = Uuid::randomBytes();
        $storefrontSalesChannelId = Uuid::randomBytes();
        $salesChannelId = Uuid::randomBytes();
        $salesChannelDomainId = Uuid::randomBytes();
        $currencyId = Uuid::randomBytes();

        $rows = [[
            'test.id' => $id,
            'test.productStreamId' => $productStreamId,
            'test.storefrontSalesChannelId' => $storefrontSalesChannelId,
            'test.salesChannelId' => $salesChannelId,
            'test.salesChannelDomainId' => $salesChannelDomainId,
            'test.currencyId' => $currencyId,
            'test.fileName' => 'feed.jsonl',
            'test.accessKey' => 'access-key',
            'test.encoding' => ProductExportEntity::ENCODING_UTF8,
            'test.fileFormat' => ProductExportEntity::FILE_FORMAT_JSONL,
            'test.provider' => 'open-ai',
            'test.feedLabel' => 'US_ELECTRONICS',
            'test.includeVariants' => 1,
            'test.generateByCronjob' => 0,
            'test.isRunning' => 1,
            'test.generatedAt' => '2026-03-23 10:15:00.000',
            'test.interval' => 60,
            'test.headerTemplate' => '',
            'test.bodyTemplate' => '{"item_id":"1"}',
            'test.footerTemplate' => '',
            'test.pausedSchedule' => 0,
            'test.createdAt' => '2026-03-23 10:15:00.000',
        ]];

        $structs = $this->hydrator->hydrate(
            new ProductExportCollection(),
            $definition->getEntityClass(),
            $definition,
            $rows,
            'test',
            Context::createDefaultContext()
        );

        $entity = $structs->first();

        static::assertInstanceOf(ProductExportEntity::class, $entity);
        static::assertSame(Uuid::fromBytesToHex($id), $entity->getId());
        static::assertSame(Uuid::fromBytesToHex($productStreamId), $entity->getProductStreamId());
        static::assertSame(Uuid::fromBytesToHex($storefrontSalesChannelId), $entity->getStorefrontSalesChannelId());
        static::assertSame(Uuid::fromBytesToHex($salesChannelId), $entity->getSalesChannelId());
        static::assertSame(Uuid::fromBytesToHex($salesChannelDomainId), $entity->getSalesChannelDomainId());
        static::assertSame(Uuid::fromBytesToHex($currencyId), $entity->getCurrencyId());
        static::assertSame('open-ai', $entity->getProvider());
        static::assertSame('US_ELECTRONICS', $entity->getFeedLabel());
        static::assertSame('2026-03-23 10:15:00', $entity->getGeneratedAt()?->format('Y-m-d H:i:s'));
    }

    public function testHydrationAssignsNullProviderWhenColumnIsPresentButEmpty(): void
    {
        $definition = $this->definitionInstanceRegistry->get(ProductExportDefinition::class);

        $rows = [[
            'test.id' => Uuid::randomBytes(),
            'test.productStreamId' => Uuid::randomBytes(),
            'test.storefrontSalesChannelId' => Uuid::randomBytes(),
            'test.salesChannelId' => Uuid::randomBytes(),
            'test.salesChannelDomainId' => Uuid::randomBytes(),
            'test.currencyId' => Uuid::randomBytes(),
            'test.fileName' => 'feed.jsonl',
            'test.accessKey' => 'access-key',
            'test.encoding' => ProductExportEntity::ENCODING_UTF8,
            'test.fileFormat' => ProductExportEntity::FILE_FORMAT_JSONL,
            'test.provider' => null,
            'test.includeVariants' => 0,
            'test.generateByCronjob' => 0,
            'test.isRunning' => 0,
            'test.interval' => 60,
            'test.headerTemplate' => '',
            'test.bodyTemplate' => '{"item_id":"1"}',
            'test.footerTemplate' => '',
            'test.pausedSchedule' => 0,
            'test.createdAt' => '2026-03-23 10:15:00.000',
        ]];

        $structs = $this->hydrator->hydrate(
            new ProductExportCollection(),
            $definition->getEntityClass(),
            $definition,
            $rows,
            'test',
            Context::createDefaultContext()
        );

        $entity = $structs->first();

        static::assertInstanceOf(ProductExportEntity::class, $entity);
        static::assertNull($entity->getProvider());
    }

    public function testHydrationAssignsNullFeedLabelWhenColumnIsPresentButEmpty(): void
    {
        $definition = $this->definitionInstanceRegistry->get(ProductExportDefinition::class);

        $rows = [[
            'test.id' => Uuid::randomBytes(),
            'test.productStreamId' => Uuid::randomBytes(),
            'test.storefrontSalesChannelId' => Uuid::randomBytes(),
            'test.salesChannelId' => Uuid::randomBytes(),
            'test.salesChannelDomainId' => Uuid::randomBytes(),
            'test.currencyId' => Uuid::randomBytes(),
            'test.fileName' => 'feed.xml',
            'test.accessKey' => 'access-key',
            'test.encoding' => ProductExportEntity::ENCODING_UTF8,
            'test.fileFormat' => ProductExportEntity::FILE_FORMAT_XML,
            'test.feedLabel' => null,
            'test.includeVariants' => 0,
            'test.generateByCronjob' => 0,
            'test.isRunning' => 0,
            'test.interval' => 60,
            'test.headerTemplate' => '',
            'test.bodyTemplate' => '<item></item>',
            'test.footerTemplate' => '',
            'test.pausedSchedule' => 0,
            'test.createdAt' => '2026-05-19 10:00:00.000',
        ]];

        $structs = $this->hydrator->hydrate(
            new ProductExportCollection(),
            $definition->getEntityClass(),
            $definition,
            $rows,
            'test',
            Context::createDefaultContext()
        );

        $entity = $structs->first();

        static::assertInstanceOf(ProductExportEntity::class, $entity);
        static::assertNull($entity->getFeedLabel());
    }
}
