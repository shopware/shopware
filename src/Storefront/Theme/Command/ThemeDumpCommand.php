<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigDumper;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
     */
    public function __construct(
        private readonly StorefrontPluginRegistryInterface $pluginRegistry,
        private readonly ThemeFileResolver $themeFileResolver,
        private readonly EntityRepository $themeRepository,
        private readonly string $projectDir,
        private readonly StaticFileConfigDumper $staticFileConfigDumper
    ) {
        parent::__construct();
        $this->context = Context::createCLIContext();
    }

    protected function configure(): void
    {
        $this->addArgument('theme-id', InputArgument::OPTIONAL, 'Theme ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('theme.salesChannels.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addAssociation('salesChannels.domains');

        $id = $input->getArgument('theme-id');
        if ($id !== null) {
            $criteria->setIds([$id]);
        }

        $themes = $this->themeRepository->search($criteria, $this->context);

        if ($themes->count() === 0) {
            $this->io->error('No theme found which is connected to a storefront sales channel');

            return self::FAILURE;
        }

        $dump = [];

        /** @var ThemeEntity $themeEntity */
        foreach ($themes as $themeEntity) {
            $technicalName = $this->getTechnicalName($themeEntity->getId());
            if ($technicalName === null) {
                $this->io->error('No theme found');

                continue;
            }

            $themeConfig = $this->pluginRegistry->getConfigurations()->getByTechnicalName($technicalName);
            if ($themeConfig === null) {
                $this->io->error(sprintf('No theme config found for theme "%s"', $themeEntity->getName()));

                continue;
            }

            /** @var SalesChannelEntity|null $firstSalesChannel */
            $firstSalesChannel = $themeEntity->getSalesChannels()?->first();

            /** @var SalesChannelDomainEntity|null $firstDomain */
            $firstDomain = $firstSalesChannel?->getDomains()?->first();
            if ($firstDomain === null) {
                $this->io->error(sprintf('No domain found for theme "%s"', $themeEntity->getName()));

                continue;
            }

            $dump[$themeEntity->getTechnicalName()] = array_merge_recursive($dump, $this->themeFileResolver->resolveFiles(
                $themeConfig,
                $this->pluginRegistry->getConfigurations(),
                true
            ));

            $dump[$themeEntity->getTechnicalName()]['domain'] = $firstDomain->getUrl();
            $dump[$themeEntity->getTechnicalName()]['themeName'] = $themeEntity->getName();
            $dump[$themeEntity->getTechnicalName()]['basePath'] = $themeConfig->getBasePath();
        }

        file_put_contents(
            $this->projectDir . \DIRECTORY_SEPARATOR . 'var' . \DIRECTORY_SEPARATOR . 'theme-files.json',
            json_encode($dump, \JSON_PRETTY_PRINT)
        );

        $this->staticFileConfigDumper->dumpConfig($this->context);

        return self::SUCCESS;
    }

    private function getTechnicalName(string $themeId): ?string
    {
        $technicalName = null;

        do {
            /** @var ThemeEntity|null $theme */
            $theme = $this->themeRepository->search(new Criteria([$themeId]), $this->context)->first();

            if (!$theme instanceof ThemeEntity) {
                break;
            }

            $technicalName = $theme->getTechnicalName();
            $themeId = $theme->getParentThemeId();
        } while ($technicalName === null && $themeId !== null);

        return $technicalName;
    }
}
