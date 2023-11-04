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
            ->addOption('active-only', 'a', InputOption::VALUE_NONE, 'Compile themes only for active  sales channels');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();
        $this->io->writeln('Start theme compilation');

        foreach ($this->themeProvider->load($context, $input->getOption('active-only')) as $salesChannelId => $themeId) {
            $this->io->block(\sprintf('Compiling theme for sales channel for : %s', $salesChannelId));

            $start = microtime(true);
            $this->themeService->compileTheme($salesChannelId, $themeId, $context, null, !$input->getOption('keep-assets'));
            $this->io->note(sprintf('Took %f seconds', microtime(true) - $start));
        }

        return self::SUCCESS;
    }
}
