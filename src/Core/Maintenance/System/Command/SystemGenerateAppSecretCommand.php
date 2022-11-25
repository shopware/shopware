<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Command;

use Defuse\Crypto\Key;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package core
 *
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'system:generate-app-secret',
    description: 'Generates a new app secret',
)]
class SystemGenerateAppSecretCommand extends Command
{
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = Key::createNewRandomKey();

        $output->writeln($key->saveToAsciiSafeString());

        return self::SUCCESS;
    }
}
