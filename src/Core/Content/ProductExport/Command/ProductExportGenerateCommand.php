<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Command;

use Shopware\Core\Content\ProductExport\Service\ProductExporterInterface;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\SalesChannelNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'product-export:generate',
    description: 'Generates a product export file',
)]
#[Package('sales-channel')]
class ProductExportGenerateCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly ProductExporterInterface $productExportService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignore cache and force generation')
            ->addOption('include-inactive', 'i', InputOption::VALUE_NONE, 'Include inactive exports')
            ->addArgument('sales-channel-id', InputArgument::REQUIRED, 'Sales channel ID of the corresponding Storefront sales channel to generate exports for')
            ->addArgument('product-export-id', InputArgument::OPTIONAL, 'Generate specific export ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $productExportId = $input->getArgument('product-export-id');
        $salesChannelId = $input->getArgument('sales-channel-id');
        $forceGeneration = $input->getOption('force');
        $includeInactive = $input->getOption('include-inactive');

        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);

        if ($salesChannelContext->getSalesChannel()->getTypeId() !== Defaults::SALES_CHANNEL_TYPE_STOREFRONT) {
            throw new SalesChannelNotFoundException();
        }

        $this->productExportService->export(
            $salesChannelContext,
            new ExportBehavior($forceGeneration, $includeInactive),
            $productExportId
        );

        return self::SUCCESS;
    }
}
