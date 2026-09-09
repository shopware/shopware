<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Commands;

use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapSalesChannelLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('discovery')]
#[AsCommand(
    name: 'sitemap:generate',
    description: 'Generates sitemap files',
)]
class SitemapGenerateCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SitemapSalesChannelLoader $salesChannelLoader,
        private readonly SitemapExporterInterface $sitemapExporter,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('salesChannelId', 'i', InputOption::VALUE_OPTIONAL, 'Generate sitemap only for for this sales channel')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force generation, even if generation has been locked by some other process'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $salesChannelId = $input->getOption('salesChannelId');

        $context = Context::createCLIContext();

        $salesChannels = $this->salesChannelLoader->loadSalesChannels($context, $salesChannelId);

        foreach ($salesChannels as $salesChannel) {
            foreach ($this->salesChannelLoader->getLanguageIds($salesChannel) as $languageId) {
                $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $languageId]);

                $output->writeln(\sprintf('Generating sitemaps for sales channel %s (%s) with and language %s...', $salesChannel->getId(), $salesChannel->getName() ?? '', $languageId));

                try {
                    $this->generateSitemap($salesChannelContext, $input->getOption('force'));
                } catch (AlreadyLockedException $exception) {
                    $output->writeln(\sprintf('ERROR: %s', $exception->getMessage()));
                }
            }
        }

        $output->writeln('done!');

        return self::SUCCESS;
    }

    private function generateSitemap(SalesChannelContext $salesChannelContext, bool $force, ?string $lastProvider = null, ?int $offset = null): void
    {
        $result = $this->sitemapExporter->generate($salesChannelContext, $force, $lastProvider, $offset);
        if ($result->isFinish() === false) {
            $this->generateSitemap($salesChannelContext, $force, $result->getProvider(), $result->getOffset());
        }
    }
}
