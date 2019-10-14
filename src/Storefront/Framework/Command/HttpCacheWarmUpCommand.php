<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Command;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HttpCacheWarmUpCommand extends Command
{
    /**
     * @var CacheWarmer
     */
    private $warmer;

    public function __construct(CacheWarmer $warmer)
    {
        parent::__construct('http:cache:warm:up');
        $this->warmer = $warmer;
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setName('http:cache:warm:up');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cacheId = Uuid::randomHex();

        $this->warmer->warmUp($cacheId);

        return null;
    }
}
