<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Command;

use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmerSender;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HttpCacheWarmupCommand extends Command
{
    /**
     * @var CacheWarmerSender
     */
    private $sender;

    public function __construct(CacheWarmerSender $sender)
    {
        parent::__construct('http:cache:warmup');
        $this->sender = $sender;
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setName('http:cache:warmup');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->sender->send();
        $output->writeln('Warm up signal sent. Warm up will be done by message queue worker');

        return null;
    }
}
