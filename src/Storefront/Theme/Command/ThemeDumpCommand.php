<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigDumper;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'theme:dump',
    description: 'Dump the theme configuration',
)]
#[Package('storefront')]
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
        private readonly StorefrontPluginRegistryInterface $pluginRegistry,
        private readonly ThemeFileResolver $themeFileResolver,
        private readonly EntityRepository $themeRepository,
        private readonly string $projectDir,
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('theme.salesChannels.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addAssociation('salesChannels.domains');

        $themeId = $input->getArgument('theme-id');

        if (!$themeId) {
            $helper = $this->getHelper('question');

            $choices = $this->getThemeChoices();

            if (\count($choices) > 1) {
                $question = new ChoiceQuestion('Please select a theme:', $choices);
                $themeName = $helper->ask($input, $output, $question);

                \assert(\is_string($themeName));

                $criteria->addFilter(new EqualsFilter('name', $themeName));
            }
        } else {
            $criteria->setIds([$themeId]);
        }

        $themes = $this->themeRepository->search($criteria, $this->context);

        if ($themes->count() === 0) {
            $this->io->error('No theme found which is connected to a storefront sales channel');

            return self::FAILURE;
        }

        /** @var ThemeEntity $themeEntity */
        $themeEntity = $themes->first();

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

        $fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($themeConfig);

        $domainUrl = $input->getArgument('domain-url');
        $domainUrl = $domainUrl ?? $this->askForDomainUrlIfMoreThanOneExists($themeEntity, $input, $output);

        if ($domainUrl === null) {
            $this->io->error(\sprintf('No domain URL for theme %s found', $themeEntity->getTechnicalName()));

            return self::FAILURE;
        }

        $dump['domainUrl'] = $domainUrl;
        $dump['basePath'] = $this->stripProjectDir($fs->location);

        file_put_contents(
            $this->projectDir . \DIRECTORY_SEPARATOR . 'var' . \DIRECTORY_SEPARATOR . 'theme-files.json',
            json_encode($dump, \JSON_PRETTY_PRINT)
        );

        $this->staticFileConfigDumper->dumpConfig($this->context);

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

    private function askForDomainUrlIfMoreThanOneExists(ThemeEntity $themeEntity, InputInterface $input, OutputInterface $output): ?string
    {
        $salesChannels = $themeEntity->getSalesChannels()?->filterByTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        if (!$salesChannels) {
            return null;
        }

        $domainUrls = [];

        foreach ($salesChannels as $salesChannel) {
            if (!$salesChannel->getDomains()?->count()) {
                continue;
            }

            foreach ($salesChannel->getDomains() as $domain) {
                $domainUrls[] = $domain->getUrl();
            }
        }

        if (\count($domainUrls) > 1) {
            $helper = $this->getHelper('question');

            $question = new ChoiceQuestion('Please select a domain url:', $domainUrls);
            $domainUrl = $helper->ask($input, $output, $question);

            \assert(filter_var($domainUrl, \FILTER_VALIDATE_URL));

            return $domainUrl;
        }

        return $domainUrls[0] ?? null;
    }

    private function stripProjectDir(string $path): string
    {
        if (str_starts_with($path, $this->projectDir)) {
            return substr($path, \strlen($this->projectDir) + 1);
        }

        return $path;
    }

    private function getTechnicalName(string $themeId): ?string
    {
        $technicalName = null;

        do {
            $theme = $this->themeRepository->search(new Criteria([$themeId]), $this->context)->first();

            if (!$theme instanceof ThemeEntity) {
                break;
            }

            $technicalName = $theme->getTechnicalName();
            $parentThemeId = $theme->getParentThemeId();
        } while ($technicalName === null && $parentThemeId !== null);

        return $technicalName;
    }
}
