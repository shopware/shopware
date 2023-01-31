<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'http:cache:warm:up',
    description: 'Warm up the http cache',
)]
#[Package('storefront')]
class HttpCacheWarmUpCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly CacheWarmer $warmer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('keep-cache', null, InputOption::VALUE_NONE, 'Keeps the same cache id so no cache invalidation is triggered');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheId = null;

        if (!$input->getOption('keep-cache')) {
            $cacheId = Uuid::randomHex();
        }

        $this->warmer->warmUp($cacheId);

        return self::SUCCESS;
    }
}
