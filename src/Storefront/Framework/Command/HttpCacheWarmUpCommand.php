<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Command;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HttpCacheWarmUpCommand extends Command
{
    protected static $defaultName = 'http:cache:warm:up';

    /**
     * @var CacheWarmer
     */
    private $warmer;

    public function __construct(CacheWarmer $warmer)
    {
        parent::__construct();
        $this->warmer = $warmer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheId = Uuid::randomHex();

        $this->warmer->warmUp($cacheId);

        return 0;
    }
}
