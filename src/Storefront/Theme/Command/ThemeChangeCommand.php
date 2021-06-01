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

    /**
     * @var ThemeService
     */
    private $themeService;

    /**
     * @var StorefrontPluginRegistryInterface
     */
    private $pluginRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeSalesChannelRepository;

    public function __construct(
        ThemeService $themeService,
        StorefrontPluginRegistryInterface $pluginRegistry,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $themeRepository,
        EntityRepositoryInterface $themeSalesChannelRepository
    ) {
        parent::__construct();

        $this->themeService = $themeService;
        $this->pluginRegistry = $pluginRegistry;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->themeRepository = $themeRepository;
        $this->themeSalesChannelRepository = $themeSalesChannelRepository;
        $this->context = Context::createDefaultContext();
    }

    protected function configure(): void
    {
        $this->addArgument('theme-name', InputArgument::OPTIONAL, 'Theme name');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Set theme for all sales channel');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $this->context)->getEntities();

        if (!$input->getOption('all')) {
            $question = new ChoiceQuestion('Please select a sales channel:', $this->getSalesChannelChoices($salesChannels));
            $answer = $helper->ask($input, $output, $question);
            $parsedSalesChannel = $this->parseSalesChannelAnswer($answer, $salesChannels);
            if ($parsedSalesChannel === null) {
                return self::FAILURE;
            }
            $salesChannels = new SalesChannelCollection([$parsedSalesChannel]);
        }

        if (!$input->getArgument('theme-name')) {
            $question = new ChoiceQuestion('Please select a theme:', $this->getThemeChoices());
            $themeName = $helper->ask($input, $output, $question);
        } else {
            $themeName = $input->getArgument('theme-name');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $themeName));

        $themes = $this->themeRepository->search($criteria, $this->context);

        foreach ($salesChannels as $salesChannel) {
            $this->io->writeln(sprintf('Set "%s" as new theme for sales channel "%s"', $themeName, $salesChannel->getName()));

            if ($themes->count() === 0) {
                $this->io->error('Invalid theme name');

                return self::FAILURE;
            }

            /** @var ThemeEntity $theme */
            $theme = $themes->first();

            $this->themeSalesChannelRepository->upsert([[
                'themeId' => $theme->getId(),
                'salesChannelId' => $salesChannel->getId(),
            ]], $this->context);

            $this->io->writeln(sprintf('Compiling theme %s for sales channel %s', $theme->getId(), $theme->getName()));
            $this->themeService->compileTheme($salesChannel->getId(), $theme->getId(), $this->context);
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

    private function parseSalesChannelAnswer(string $answer, SalesChannelCollection $salesChannels): ?SalesChannelEntity
    {
        $parts = explode('|', $answer);
        $salesChannelId = trim(array_pop($parts));
        $salesChannel = $salesChannels->get($salesChannelId);

        if (!$salesChannelId) {
            $this->io->error('Invalid answer');

            return null;
        }

        return $salesChannel;
    }
}
