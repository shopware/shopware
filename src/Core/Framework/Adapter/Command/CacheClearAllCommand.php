<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Command;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('core')]
#[AsCommand(name: 'cache:clear:all', description: 'Clear all caches/pools, invalidates expired tags, removes old system and twig cache directories')]
class CacheClearAllCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheClearer $cacheClearer,
        private readonly string $env,
        private readonly bool $debug,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            <<<'EOF'
The <info>%command.name%</info> command clears the application cache and pools, invalidates expired tags, removes old system and twig cache directories for a given environment
and debug mode:

    <info>php %command.full_name% --env=dev</info>
    <info>php %command.full_name% --env=prod --no-debug</info>
EOF
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

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
