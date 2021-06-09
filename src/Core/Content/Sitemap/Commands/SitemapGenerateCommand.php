<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Commands;

use Shopware\Core\Content\Sitemap\Event\SitemapSalesChannelCriteriaEvent;
use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SitemapGenerateCommand extends Command
{
    public static $defaultName = 'sitemap:generate';

    private EntityRepositoryInterface $salesChannelRepository;

    private SitemapExporterInterface $sitemapExporter;

    private AbstractSalesChannelContextFactory $salesChannelContextFactory;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        SitemapExporterInterface $sitemapExporter,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct();

        $this->salesChannelRepository = $salesChannelRepository;
        $this->sitemapExporter = $sitemapExporter;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $salesChannelId = $input->getOption('salesChannelId');

        $context = Context::createDefaultContext();

        $criteria = $this->createCriteria($salesChannelId);

        $this->eventDispatcher->dispatch(
            new SitemapSalesChannelCriteriaEvent($criteria, $context)
        );

        $salesChannels = $this->salesChannelRepository->search($criteria, $context);

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $languageIds = $salesChannel->getDomains()->map(function (SalesChannelDomainEntity $salesChannelDomain) {
                return $salesChannelDomain->getLanguageId();
            });

            $languageIds = array_unique($languageIds);

            foreach ($languageIds as $languageId) {
                $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $languageId]);
                $output->writeln(sprintf('Generating sitemaps for sales channel %s (%s) with and language %s...', $salesChannel->getId(), $salesChannel->getName(), $languageId));

                try {
                    $this->generateSitemap($salesChannelContext, $input->getOption('force'));
                } catch (AlreadyLockedException $exception) {
                    $output->writeln(sprintf('ERROR: %s', $exception->getMessage()));
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

    private function createCriteria(?string $salesChannelId = null): Criteria
    {
        $criteria = $salesChannelId ? new Criteria([$salesChannelId]) : new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [new EqualsFilter('domains.id', null)]
        ));

        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        return $criteria;
    }
}
