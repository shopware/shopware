<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Command\ProductExportGenerateCommand;
use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Content\ProductExport\Service\ProductExporterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductExportGenerateCommand::class)]
class ProductExportGenerateCommandTest extends TestCase
{
    private CommandTester $commandTester;

    private ProductExporterInterface&Stub $productExporter;

    private AbstractSalesChannelContextFactory&Stub $salesChannelContextFactory;

    protected function setUp(): void
    {
        $this->salesChannelContextFactory = static::createStub(AbstractSalesChannelContextFactory::class);
        $this->productExporter = static::createStub(ProductExporterInterface::class);
        $this->commandTester = $this->createCommandTester();
    }

    public function testExecutionWithInvalidSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId($salesChannelId);
        $salesChannelEntity->setTypeId(Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannelEntity);

        $this->salesChannelContextFactory->method('create')->willReturn($salesChannelContext);

        $this->expectExceptionObject(ProductExportException::salesChannelNotAllowed());

        $this->commandTester->execute([
            'sales-channel-id' => $salesChannelId,
        ]);
    }

    /**
     * @param Defaults::SALES_CHANNEL_TYPE_* $typeId
     */
    #[DataProvider('allowedSalesChannelTypeProvider')]
    public function testExecuteWithValidData(string $typeId): void
    {
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId($salesChannelId);
        $salesChannelEntity->setTypeId($typeId);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannelEntity);

        $this->salesChannelContextFactory->method('create')->willReturn($salesChannelContext);

        $productExporter = $this->createMock(ProductExporterInterface::class);
        $productExporter->expects($this->once())->method('export');

        $commandTester = $this->createCommandTester($productExporter);
        $commandTester->execute([
            'sales-channel-id' => $salesChannelId,
            '--force' => false,
            '--include-inactive' => true,
        ]);

        static::assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function allowedSalesChannelTypeProvider(): iterable
    {
        yield 'storefront' => [Defaults::SALES_CHANNEL_TYPE_STOREFRONT];
        yield 'headless' => [Defaults::SALES_CHANNEL_TYPE_API];
    }

    private function createCommandTester(?ProductExporterInterface $productExporter = null): CommandTester
    {
        $command = new ProductExportGenerateCommand($this->salesChannelContextFactory, $productExporter ?? $this->productExporter);

        return new CommandTester($command);
    }
}
