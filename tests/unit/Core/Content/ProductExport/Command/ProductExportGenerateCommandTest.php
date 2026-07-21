<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ProductExport\Command\ProductExportGenerateCommand;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Content\ProductExport\Service\ProductExporterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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

    /**
     * @var EntityRepository<ProductExportCollection>&Stub
     */
    private EntityRepository&Stub $productExportRepository;

    private LoggerInterface&Stub $logger;

    protected function setUp(): void
    {
        $this->salesChannelContextFactory = static::createStub(AbstractSalesChannelContextFactory::class);
        $this->productExporter = static::createStub(ProductExporterInterface::class);
        $this->productExportRepository = static::createStub(EntityRepository::class);
        $this->logger = static::createStub(LoggerInterface::class);
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

    public function testExecuteWithValidData(): void
    {
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createSalesChannelContextStub($salesChannelId, $context);

        $this->salesChannelContextFactory->method('create')->willReturn($salesChannelContext);
        $this->productExportRepository->method('search')->willReturn($this->createSearchResult([], $context));

        $productExporter = $this->createMock(ProductExporterInterface::class);
        $productExporter->expects($this->once())->method('export');

        $commandTester = $this->createCommandTester($productExporter);
        $commandTester->execute([
            'sales-channel-id' => $salesChannelId,
            '--force' => false,
            '--include-inactive' => true,
        ]);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringNotContainsString('scheduler', $commandTester->getDisplay());
    }

    public function testExecuteWarnsAboutSchedulerManagedExportAndStillGenerates(): void
    {
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createSalesChannelContextStub($salesChannelId, $context);

        $this->salesChannelContextFactory->method('create')->willReturn($salesChannelContext);

        $schedulerManagedExport = new ProductExportEntity();
        $schedulerManagedExport->setId(Uuid::randomHex());
        $schedulerManagedExport->setFileName('scheduler.csv');
        $schedulerManagedExport->setGenerateByCronjob(true);

        $this->productExportRepository
            ->method('search')
            ->willReturn($this->createSearchResult([$schedulerManagedExport], $context));

        // Generation is still triggered; the export service transparently skips the scheduler-managed
        // export while generating the remaining ones, so the batch is never aborted.
        $productExporter = $this->createMock(ProductExporterInterface::class);
        $productExporter->expects($this->once())->method('export');

        $commandTester = $this->createCommandTester($productExporter);
        $commandTester->execute([
            'sales-channel-id' => $salesChannelId,
        ]);

        $display = $commandTester->getDisplay();
        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringContainsString('scheduler.csv', $display);
        static::assertStringContainsString('scheduler', $display);
        static::assertStringContainsString('[WARNING]', $display);
    }

    public function testExecuteWithForceDoesNotWarnAboutSchedulerManagedExports(): void
    {
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createSalesChannelContextStub($salesChannelId, $context);

        $this->salesChannelContextFactory->method('create')->willReturn($salesChannelContext);

        // With --force the repository is never queried for scheduler-managed exports.
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('search');

        $productExporter = $this->createMock(ProductExporterInterface::class);
        $productExporter->expects($this->once())->method('export');

        $command = new ProductExportGenerateCommand(
            $this->salesChannelContextFactory,
            $productExporter,
            $repository,
            $this->logger
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'sales-channel-id' => $salesChannelId,
            '--force' => true,
        ]);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringNotContainsString('scheduler', $commandTester->getDisplay());
    }

    private function createSalesChannelContextStub(string $salesChannelId, Context $context): SalesChannelContext&Stub
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getSalesChannelId')->willReturn($salesChannelId);

        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId($salesChannelId);
        $salesChannelEntity->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannelEntity);

        return $salesChannelContext;
    }

    /**
     * @param list<ProductExportEntity> $exports
     *
     * @return EntitySearchResult<ProductExportCollection>
     */
    private function createSearchResult(array $exports, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(
            'product_export',
            \count($exports),
            new ProductExportCollection($exports),
            null,
            new Criteria(),
            $context
        );
    }

    private function createCommandTester(?ProductExporterInterface $productExporter = null): CommandTester
    {
        $command = new ProductExportGenerateCommand(
            $this->salesChannelContextFactory,
            $productExporter ?? $this->productExporter,
            $this->productExportRepository,
            $this->logger
        );

        return new CommandTester($command);
    }
}
