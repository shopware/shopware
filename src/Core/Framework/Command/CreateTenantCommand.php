<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Provisioning\TenantProvisioner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTenantCommand extends Command
{
    /**
     * @var TenantProvisioner
     */
    private $tenantProvisioner;

    public function __construct(TenantProvisioner $tenantProvisioner)
    {
        parent::__construct();

        $this->tenantProvisioner = $tenantProvisioner;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var string $tenantId */
        $tenantId = $input->getOption('tenant-id');

        $tenantId = $this->tenantProvisioner->provision($tenantId);

        $output->writeln('Created tenant with id: ' . $tenantId);
    }
}
