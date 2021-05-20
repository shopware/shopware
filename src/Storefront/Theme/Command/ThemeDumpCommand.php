<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ThemeDumpCommand extends Command
{
    protected static $defaultName = 'theme:dump';

    /**
     * @var StorefrontPluginRegistryInterface
     */
    private $pluginRegistry;

    /**
     * @var ThemeFileResolver
     */
    private $themeFileResolver;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var SymfonyStyle
     */
    private $io;

    public function __construct(
        StorefrontPluginRegistryInterface $pluginRegistry,
        ThemeFileResolver $themeFileResolver,
        EntityRepositoryInterface $themeRepository,
        string $projectDir
    ) {
        parent::__construct();

        $this->pluginRegistry = $pluginRegistry;
        $this->themeFileResolver = $themeFileResolver;
        $this->themeRepository = $themeRepository;
        $this->projectDir = $projectDir;
        $this->context = Context::createDefaultContext();
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

        $id = $input->getArgument('theme-id');
        if ($id !== null) {
            $criteria->setIds([$id]);
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
            $this->io->error(sprintf('No theme config found for theme "%s"', $themeEntity->getName()));

            return self::FAILURE;
        }

        $dump = $this->themeFileResolver->resolveFiles(
            $themeConfig,
            $this->pluginRegistry->getConfigurations(),
            true
        );

        $dump['basePath'] = $themeConfig->getBasePath();

        file_put_contents(
            $this->projectDir . \DIRECTORY_SEPARATOR . 'var' . \DIRECTORY_SEPARATOR . 'theme-files.json',
            json_encode($dump, \JSON_PRETTY_PRINT)
        );

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
