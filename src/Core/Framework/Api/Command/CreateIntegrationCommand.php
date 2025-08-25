<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Command;

use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'integration:create',
    description: 'Create an integration and dump the key and secret',
)]
#[Package('framework')]
class CreateIntegrationCommand extends Command
{
    /**
     * @internal
     *
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     */
    public function __construct(private readonly EntityRepository $integrationRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Name of the integration')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Has admin rights')
            ->addOption('access-key', null, InputOption::VALUE_REQUIRED, 'Custom access key')
            ->addOption('secret-access-key', null, InputOption::VALUE_REQUIRED, 'Custom secret access key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('access-key') ?? AccessKeyHelper::generateAccessKey('integration');
        $secret = $input->getOption('secret-access-key') ?? AccessKeyHelper::generateSecretAccessKey();

        $this->integrationRepository->create([
            [
                'label' => $input->getArgument('name'),
                'accessKey' => $id,
                'secretAccessKey' => $secret,
                'admin' => (bool) $input->getOption('admin'),
            ],
        ], Context::createCLIContext());

        $output->writeln('SHOPWARE_ACCESS_KEY_ID=' . $id);
        $output->writeln('SHOPWARE_SECRET_ACCESS_KEY=' . $secret);

        return self::SUCCESS;
    }
}
