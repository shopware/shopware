<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Command;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Adapter\Console\TtyDetector;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('framework')]
#[AsCommand(name: 'cache:clear:all', description: 'Clear all caches/pools, invalidate expired tags, remove twig cache and delete all other kernel cache directories (may cause errors on other running instances)')]
class CacheClearAllCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheClearer $cacheClearer,
        private readonly string $env,
        private readonly bool $debug,
        private readonly TtyDetector $ttyDetector,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompt');

        $this->setHelp(
            <<<'EOF'
The <info>%command.name%</info> command clears all application caches, pools, invalidates expired tags,
and <comment>deletes all kernel cache directories except the currently active one</comment>.

<comment>Warning:</comment> If a web server is running with a different plugin configuration (different
cache hash), this command will delete its cache directory and may cause 500 errors.

In clustered environments or when web and CLI use different active plugins,
consider using <info>cache:clear</info> instead, which only clears the current cache.

Usage:
    <info>php %command.full_name% --env=dev</info>
    <info>php %command.full_name% --env=prod --no-debug</info>
    <info>php %command.full_name% --force</info>  (skip confirmation)
EOF
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $io->warning([
            'This command will delete all kernel cache directories except the currently active one.',
            'If a web server is running with a different plugin configuration, this may cause 500 errors.',
            'In clustered environments or when web and CLI use different plugins, consider using "cache:clear" instead.',
        ]);

        if (!$input->getOption('force') && $input->isInteractive() && $this->ttyDetector->isStdinTty()) {
            if (!$io->confirm('Do you want to continue?', false)) {
                $io->caution('Aborting due to user input.');

                return self::SUCCESS;
            }
        }

        try {
            $io->comment(\sprintf('Clearing the caches and pools for the <info>%s</info> environment with debug <info>%s</info>', $this->env, var_export($this->debug, true)));

            $this->cacheClearer->clear();

            $io->success(\sprintf('Caches and pools for the "%s" environment (debug=%s) was successfully cleared.', $this->env, var_export($this->debug, true)));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
