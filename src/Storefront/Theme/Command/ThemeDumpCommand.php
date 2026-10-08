<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigDumper;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Package('discovery')]
#[AsCommand(
    name: 'theme:dump',
    description: 'Dump the theme configuration',
)]
class ThemeDumpCommand extends Command
{
    private readonly Context $context;

    private SymfonyStyle $io;

    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $themeRepository
     */
    public function __construct(
        private readonly StorefrontPluginRegistry $pluginRegistry,
        private readonly ThemeFileResolver $themeFileResolver,
        private readonly EntityRepository $themeRepository,
        private readonly StaticFileConfigDumper $staticFileConfigDumper,
        private readonly ThemeFilesystemResolver $themeFilesystemResolver
    ) {
        parent::__construct();
        $this->context = Context::createCLIContext();
    }

    protected function configure(): void
    {
        $this->addArgument('theme-id', InputArgument::OPTIONAL, 'Theme ID');
        $this->addArgument('domain-url', InputArgument::OPTIONAL, 'Sales channel domain URL');
        $this->addOption('theme-name', null, InputOption::VALUE_OPTIONAL, 'Technical theme name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('theme.salesChannels.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addAssociation('salesChannels.domains');

        $themeId = $input->getArgument('theme-id');

        $themeName = $input->getOption('theme-name');

        if ($themeId !== null) {
            $criteria->setIds([$themeId]);
        } elseif ($themeName !== null) {
            $criteria->addFilter(new EqualsFilter('technicalName', $themeName));
        } else {
            $choices = $this->getThemeChoices();

            if ($input->isInteractive() && \count($choices) > 1) {
                $helper = $this->getHelper('question');
                \assert($helper instanceof QuestionHelper);

                $this->io->note($this->getThemeAssignmentInfos());
                $question = new ChoiceQuestion('Please select a theme:', $choices);
                $themeName = $helper->ask($input, $output, $question);

                \assert(\is_string($themeName));

                $criteria->addFilter(new EqualsFilter('name', $themeName));
            }
        }

        $themeEntity = $this->themeRepository->search($criteria, $this->context)->getEntities()->first();
        if (!$themeEntity instanceof ThemeEntity) {
            $this->io->error('No theme found which is connected to a storefront sales channel');

            return self::FAILURE;
        }

        $technicalName = $this->getTechnicalName($themeEntity->getId());
        if ($technicalName === null) {
            $this->io->error('No theme found');

            return self::FAILURE;
        }

        $themeConfig = $this->pluginRegistry->getConfigurations()->getByTechnicalName($technicalName);
        if ($themeConfig === null) {
            $this->io->error(\sprintf('No theme config found for theme "%s"', $themeEntity->getName()));

            return self::FAILURE;
        }

        $dump = $this->themeFileResolver->resolveFiles(
            $themeConfig,
            $this->pluginRegistry->getConfigurations(),
            true
        );

        $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($themeConfig);

        $themeName = $themeEntity->getTechnicalName() ?? $themeEntity->getId();

        // An empty argument is treated as absent, so that `theme:dump <theme-id> "$UNSET_VAR"` resolves the domain
        // instead of dumping an empty URL.
        $domainUrl = $input->getArgument('domain-url') ?: null;

        if ($domainUrl === null) {
            $domainUrls = $this->getDomainUrls($themeEntity);

            if ($domainUrls === []) {
                $this->io->error(\sprintf('No domain URL for theme %s found', $themeName));

                return self::FAILURE;
            }

            if (\count($domainUrls) === 1) {
                $domainUrl = $domainUrls[0];
            } elseif ($input->isInteractive()) {
                $domainUrl = $this->askForDomainUrl($domainUrls, $input, $output);
            } else {
                $domainUrl = $domainUrls[0];

                $this->io->warning(\sprintf(
                    'More than one domain URL is available for theme %s, using %s. Provide the domain URL as an argument to select one explicitly.',
                    $themeName,
                    $domainUrl
                ));
            }
        }

        \assert(\is_string($domainUrl));

        $dump['themeId'] = $themeEntity->getId();
        $dump['technicalName'] = $themeConfig->getTechnicalName();
        $dump['domainUrl'] = $domainUrl;

        $this->staticFileConfigDumper->dumpConfigInVar('theme-files.json', $dump);

        $this->staticFileConfigDumper->dumpConfig($this->context);

        $this->io->writeln(\sprintf('Theme `%s` config dumped to file: %s', $themeName, 'theme-files.json'));

        return self::SUCCESS;
    }

    /**
     * @return array<string>
     */
    protected function getThemeChoices(): array
    {
        $choices = [];

        $themes = $this->themeRepository->search(new Criteria(), Context::createCLIContext())->getEntities();

        foreach ($themes as $theme) {
            $choices[] = $theme->getName();
        }

        return $choices;
    }

    private function getThemeAssignmentInfos(): string
    {
        $choices = 'Theme assignment:' . \PHP_EOL;

        $criteria = new Criteria();
        $criteria->addAssociation('salesChannels');
        $themes = $this->themeRepository->search($criteria, $this->context)->getEntities();

        foreach ($themes as $theme) {
            $themeName = $theme->getName();
            $salesChannels = $theme->getSalesChannels()?->filterByTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
            $channelCount = $salesChannels ? $salesChannels->count() : 0;

            if ($channelCount > 0) {
                $choices .=
                    \sprintf(
                        '%s || Assigned to: %s',
                        $themeName,
                        $salesChannels ? implode(', ', $salesChannels->map(static fn (SalesChannelEntity $channel) => $channel->getName())) : ''
                    );
                $choices .= \PHP_EOL;
                continue;
            }

            $choices .= \sprintf('%s || Not assigned to any storefront channel', $themeName);
            $choices .= \PHP_EOL;
        }

        return $choices;
    }

    /**
     * @return list<string>
     */
    private function getDomainUrls(ThemeEntity $themeEntity): array
    {
        $salesChannels = $themeEntity->getSalesChannels()?->filterByTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $domainUrls = [];

        foreach ($salesChannels ?? [] as $salesChannel) {
            foreach ($salesChannel->getDomains() ?? [] as $domain) {
                $domainUrls[] = $domain->getUrl();
            }
        }

        return $domainUrls;
    }

    /**
     * @param non-empty-list<string> $domainUrls
     */
    private function askForDomainUrl(array $domainUrls, InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');
        \assert($helper instanceof QuestionHelper);

        $question = new ChoiceQuestion('Please select a domain url:', $domainUrls);
        $domainUrl = $helper->ask($input, $output, $question);

        \assert(\is_string($domainUrl));

        return $domainUrl;
    }

    private function getTechnicalName(string $themeId): ?string
    {
        $technicalName = null;

        do {
            $theme = $this->themeRepository->search(new Criteria([$themeId]), $this->context)->getEntities()->first();
            if (!$theme) {
                break;
            }

            $technicalName = $theme->getTechnicalName();
            $parentThemeId = $theme->getParentThemeId();
            if ($parentThemeId !== null) {
                $themeId = $parentThemeId;
            }
        } while ($technicalName === null && $themeId !== '');

        return $technicalName;
    }
}
