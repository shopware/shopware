<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'theme:compile',
    description: 'Compile the theme',
)]
#[Package('storefront')]
class ThemeCompileCommand extends Command
{
    private SymfonyStyle $io;

    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly AbstractAvailableThemeProvider $themeProvider
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('keep-assets', 'k', InputOption::VALUE_NONE, 'Keep current assets, do not delete them')
            ->addOption('active-only', 'a', InputOption::VALUE_NONE, 'Compile themes only for active sales channels')
            ->addOption('only', 'o', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Compile themes only for given sales channels ids')
            ->addOption('skip', 's', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Skip compiling themes for given sales channels ids')
            ->addOption('only-themes', 'O', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Compile only themes for given theme ids')
            ->addOption('skip-themes', 'S', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Skip compiling themes for given theme ids')
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Compile the theme synchronously')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();
        if ($input->getOption('sync')) {
            $context->addState(ThemeService::STATE_NO_QUEUE);
        }

        $this->io->writeln('Start theme compilation');

        $onlySalesChannel = ((array) $input->getOption('only')) ?: null;
        $skipSalesChannel = ((array) $input->getOption('skip')) ?: null;
        if ($onlySalesChannel !== null && $skipSalesChannel !== null
            && \count(array_intersect($onlySalesChannel, $skipSalesChannel)) > 0) {
            $this->io->error('The sales channel includes and skips contain contradicting entries:' . implode(
                ', ',
                array_intersect($onlySalesChannel, $skipSalesChannel)
            ));

            return self::FAILURE;
        }

        $onlyThemes = ((array) $input->getOption('only-themes')) ?: null;
        $skipThemes = ((array) $input->getOption('skip-themes')) ?: null;
        if ($onlyThemes !== null && $skipThemes !== null
            && \count(array_intersect($onlyThemes, $skipThemes)) > 0) {
            $this->io->error('The theme includes and skips contain contradicting entries:' . implode(
                ', ',
                array_intersect($onlyThemes, $skipThemes)
            ));

            return self::FAILURE;
        }

        foreach ($this->themeProvider->load($context, $input->getOption('active-only')) as $salesChannelId => $themeId) {
            if ($onlySalesChannel !== null && !\in_array($salesChannelId, $onlySalesChannel, true)
                || $skipSalesChannel !== null && \in_array($salesChannelId, $skipSalesChannel, true)
                || $onlyThemes !== null && !\in_array($themeId, $onlyThemes, true)
                || $skipThemes !== null && \in_array($themeId, $skipThemes, true)) {
                continue;
            }

            $this->io->block(\sprintf('Compiling theme for sales channel for : %s', $salesChannelId));

            $start = microtime(true);
            $this->themeService->compileTheme($salesChannelId, $themeId, $context, null, !$input->getOption('keep-assets'));
            $this->io->note(sprintf('Took %f seconds', microtime(true) - $start));
        }

        return self::SUCCESS;
    }
}
