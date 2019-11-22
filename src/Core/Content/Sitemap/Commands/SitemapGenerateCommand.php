<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Commands;

use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SitemapGenerateCommand extends Command
{
    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var SitemapExporterInterface
     */
    private $sitemapExporter;

    /**
     * @var SalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        SitemapExporterInterface $sitemapExporter,
        SalesChannelContextFactory $salesChannelContextFactory
    ) {
        parent::__construct();

        $this->salesChannelRepository = $salesChannelRepository;
        $this->sitemapExporter = $sitemapExporter;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('sitemap:generate')
            ->setDescription('Generates sitemaps for a given shop (or all active ones)')
            ->addOption(
                'salesChannelId',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Generate sitemap only for for this sales channel'
            )
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
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $salesChannelId = $input->getOption('salesChannelId');

        $context = Context::createDefaultContext();

        if ($salesChannelId) {
            $criteria = new Criteria([$salesChannelId]);
            $criteria->addAssociation('domains');
            $criteria->addAssociation('type');
            $salesChannels = $this->salesChannelRepository->search($criteria, $context);

            if ($salesChannels->count() === 0) {
                throw new \RuntimeException(sprintf('Could not found a sales channel with id %s', $salesChannelId));
            }
        } else {
            $criteria = new Criteria();
            $criteria->addAssociation('domains');
            $criteria->addAssociation('type');
            $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();
        }

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            if ($salesChannel->getType()->getId() === Defaults::SALES_CHANNEL_TYPE_API) {
                $output->writeln(sprintf('ignored headless sales channel %s (%s)', $salesChannel->getId(), $salesChannel->getName()));

                continue;
            }

            if (\count($salesChannel->getDomains()) === 0) {
                $languageIds = [$salesChannel->getLanguageId()];
            } else {
                $languageIds = $salesChannel->getDomains()->map(function (SalesChannelDomainEntity $salesChannelDomain) {
                    return $salesChannelDomain->getLanguageId();
                });
            }

            foreach ($languageIds as $languageId) {
                $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $languageId]);
                $output->writeln(sprintf('Generating sitemaps for sales channel %s (%s) and language %s...', $salesChannel->getId(), $salesChannel->getName(), $languageId));

                try {
                    $this->generateSitemap($salesChannelContext, $input->getOption('force'));
                } catch (AlreadyLockedException $exception) {
                    $output->writeln(sprintf('ERROR: %s', $exception->getMessage()));
                }
            }
        }

        $output->writeln('done!');

        return null;
    }

    private function generateSitemap(SalesChannelContext $salesChannelContext, bool $force, ?string $lastProvider = null, ?int $offset = null): void
    {
        $result = $this->sitemapExporter->generate($salesChannelContext, $force, $lastProvider, $offset);
        if ($result->isFinish() === false) {
            $this->generateSitemap($salesChannelContext, $force, $result->getProvider(), $result->getOffset());
        }
    }
}
