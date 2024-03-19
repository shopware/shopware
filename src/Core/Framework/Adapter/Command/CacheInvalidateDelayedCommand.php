<?php

namespace Shopware\Core\Framework\Adapter\Command;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:clear:delayed', description: 'Invalidates the delayed cache keys/tags')]
#[Package('core')]
class CacheInvalidateDelayedCommand extends Command
{

    public function __construct(
        private readonly CacheInvalidator $invalidator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->invalidator->invalidateExpired();

        return 0;
    }
}
