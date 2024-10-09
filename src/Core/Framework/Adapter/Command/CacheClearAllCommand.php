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
#[AsCommand(name: 'cache:clear:all', description: 'Clear all caches/pools')]
class CacheClearAllCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheClearer $cacheClearer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        try {
            $io->comment('Clearing the caches and pools...');

            $this->cacheClearer->clear();

            if ($output->isVerbose()) {
                $io->comment('Finished');
            }

            $io->success('All caches and pools was successfully cleared.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
