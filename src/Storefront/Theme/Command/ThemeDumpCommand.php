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
    private $cacheDir;

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
        string $cacheDir
    ) {
        parent::__construct();

        $this->pluginRegistry = $pluginRegistry;
        $this->themeFileResolver = $themeFileResolver;
        $this->themeRepository = $themeRepository;
        $this->cacheDir = $cacheDir;
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
            if (\is_array($id)) {
                $criteria->setIds($id);
            } else {
                $criteria->setIds([$id]);
            }
        }

        $themes = $this->themeRepository->search($criteria, $this->context);

        if ($themes->count() === 0) {
            $this->io->error('No theme found which is connected to a storefront sales channel');

            return 1;
        }

        /** @var ThemeEntity $themeEntity */
        $themeEntity = $themes->first();
        $themeConfig = $this->pluginRegistry->getConfigurations()->getByTechnicalName($this->getTechnicalName($themeEntity->getId()));

        $dump = $this->themeFileResolver->resolveFiles(
            $themeConfig,
            $this->pluginRegistry->getConfigurations(),
            true
        );

        $dump['basePath'] = $themeConfig->getBasePath();

        file_put_contents(
            $this->cacheDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'theme-files.json',
            json_encode($dump, JSON_PRETTY_PRINT)
        );

        return 0;
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
