<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ThemeCompileCommand extends Command
{
    protected static $defaultName = 'theme:compile';

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var ThemeService
     */
    private $themeService;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(ThemeService $themeService, EntityRepositoryInterface $salesChannelRepository)
    {
        parent::__construct();
        $this->themeService = $themeService;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function configure(): void
    {
        $this
            ->addOption('keep-assets', 'k', InputOption::VALUE_NONE, 'Keep current assets, do not delete them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();
        $this->io->writeln('Start theme compilation');
        $start = microtime(true);

        $salesChannels = $this->getSalesChannels($context);
        foreach ($salesChannels as $salesChannel) {
            /** @var ThemeCollection|null $themes */
            $themes = $salesChannel->getExtensionOfType('themes', ThemeCollection::class);
            if (!$themes || !$theme = $themes->first()) {
                continue;
            }
            $this->themeService->compileTheme($salesChannel->getId(), $theme->getId(), $context, null, !$input->getOption('keep-assets'));
        }

        $this->io->note(sprintf('Took %f seconds', microtime(true) - $start));

        return self::SUCCESS;
    }

    private function getSalesChannels(Context $context): SalesChannelCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('themes');

        /** @var SalesChannelCollection $result */
        $result = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        return $result;
    }
}
