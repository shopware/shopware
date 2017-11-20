<?php declare(strict_types=1);

namespace Shopware\Api\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpRoutesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('api:dump:routes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dumper = $this->getContainer()->get('shopware.api.route_collector');

        $dumper->collect();
    }
}
