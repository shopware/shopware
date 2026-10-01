<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Command;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This command can be used to detect if the system is installed to script a Shopware installation or update.
 *
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'system:is-installed',
    description: 'Checks if the system is installed and returns exit code 0 if Shopware is installed',
)]
class SystemIsInstalledCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if (TableHelper::tableExists($this->connection, 'migration')) {
                $io->success('Shopware is installed');

                return self::SUCCESS;
            }
        } catch (\Throwable) {
        }

        $io->error('Shopware is not installed');

        return self::FAILURE;
    }
}
