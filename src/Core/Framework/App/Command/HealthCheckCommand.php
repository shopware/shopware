<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Api\HealthCheck\Service\Manager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:health_check', description: 'Health check for the app system')]
class HealthCheckCommand extends Command
{
    public function __construct(private readonly Manager $manager)
    {
        parent::__construct(null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->manager->healthCheck();

        return Command::SUCCESS;
    }
}
