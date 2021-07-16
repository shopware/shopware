<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Framework\Context;
use Shopware\Storefront\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ThemeCompileCommand extends Command
{
    protected static $defaultName = 'theme:compile';

    private SymfonyStyle $io;

    private ThemeService $themeService;

    private AbstractAvailableThemeProvider $themeProvider;

    public function __construct(ThemeService $themeService, AbstractAvailableThemeProvider $themeProvider)
    {
        parent::__construct();
        $this->themeService = $themeService;
        $this->themeProvider = $themeProvider;
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

        foreach ($this->themeProvider->load($context) as $salesChannelId => $themeId) {
            $this->io->block(\sprintf('Compiling theme for sales channel for : %s', $salesChannelId));

            $start = microtime(true);
            $this->themeService->compileTheme($salesChannelId, $themeId, $context, null, !$input->getOption('keep-assets'));
            $this->io->note(sprintf('Took %f seconds', microtime(true) - $start));
        }

        return self::SUCCESS;
    }
}
