<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class ThemeChangeCommand extends Command
{
    protected static $defaultName = 'theme:change';

    private ThemeService $themeService;

    private StorefrontPluginRegistryInterface $pluginRegistry;

    private EntityRepositoryInterface $salesChannelRepository;

    private Context $context;

    private SymfonyStyle $io;

    private EntityRepositoryInterface $themeRepository;

    public function __construct(
        ThemeService $themeService,
        StorefrontPluginRegistryInterface $pluginRegistry,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $themeRepository
    ) {
        parent::__construct();

        $this->themeService = $themeService;
        $this->pluginRegistry = $pluginRegistry;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->themeRepository = $themeRepository;
        $this->context = Context::createDefaultContext();
    }

    protected function configure(): void
    {
        $this->addArgument('theme-name', InputArgument::OPTIONAL, 'Technical theme name');
        $this->addOption('sales-channel', 's', InputOption::VALUE_REQUIRED, 'Sales Channel ID. Can not be used together with --all.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Set theme for all sales channel Can not be used together with -s');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $themeName = $input->getArgument('theme-name');
        $salesChannelOption = $input->getOption('sales-channel');

        $this->io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        if ($input->getOption('sales-channel') && $input->getOption('all')) {
            $this->io->error('You can use either --sales-channel or --all, not both at the same time.');

            return self::INVALID;
        }

        if (!$themeName) {
            $question = new ChoiceQuestion('Please select a theme:', $this->getThemeChoices());
            $themeName = $helper->ask($input, $output, $question);
        }

        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $this->context)->getEntities();

        if ($input->getOption('all')) {
            $selectedSalesChannel = $salesChannels;
        } else {
            if (!$salesChannelOption) {
                $question = new ChoiceQuestion('Please select a sales channel:', $this->getSalesChannelChoices($salesChannels));
                $answer = $helper->ask($input, $output, $question);
                $salesChannelOption = $this->parseSalesChannelAnswer($answer);

                if ($salesChannelOption === null) {
                    return self::INVALID;
                }
            }

            if (!$salesChannels->has($salesChannelOption)) {
                $this->io->error('Could not find sales channel with ID ' . $salesChannelOption);

                return self::INVALID;
            }
            $selectedSalesChannel = [$salesChannels->get($salesChannelOption)];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $themeName));

        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($criteria, $this->context)->first();

        if ($theme === null) {
            $this->io->error('Invalid theme name');

            return self::INVALID;
        }

        /** @var SalesChannelEntity $salesChannel */
        foreach ($selectedSalesChannel as $salesChannel) {
            $this->io->writeln(sprintf('Set and compiling theme "%s" (%s) as new theme for sales channel "%s"', $themeName, $theme->getId(), $salesChannel->getName()));

            $this->themeService->assignTheme($theme->getId(), $salesChannel->getId(), $this->context);
        }

        return self::SUCCESS;
    }

    protected function getSalesChannelChoices(SalesChannelCollection $salesChannels): array
    {
        $choices = [];

        foreach ($salesChannels as $salesChannel) {
            $choices[] = $salesChannel->getName() . ' | ' . $salesChannel->getId();
        }

        return $choices;
    }

    protected function getThemeChoices(): array
    {
        $choices = [];

        foreach ($this->pluginRegistry->getConfigurations()->getThemes() as $theme) {
            $choices[] = $theme->getTechnicalName();
        }

        return $choices;
    }

    private function parseSalesChannelAnswer(string $answer): ?string
    {
        $parts = explode('|', $answer);
        $salesChannelId = trim(array_pop($parts));

        if (!$salesChannelId) {
            $this->io->error('Invalid answer');

            return null;
        }

        return $salesChannelId;
    }
}
