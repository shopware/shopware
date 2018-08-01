<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Provisioning\TenantProvisioner;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTenantCommand extends ContainerAwareCommand
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

    protected function configure()
    {
        $this->addOption('tenant-id', 'id', InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var string $tenantId */
        $tenantId = $input->getOption('tenant-id');

        $tenantId = $this->tenantProvisioner->provision($tenantId);

        $output->writeln('Created tenant with id: ' . $tenantId);
    }
}
