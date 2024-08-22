<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'system:generate-app-secret',
    description: 'Generates a new app secret',
)]
#[Package('core')]
class SystemGenerateAppSecretCommand extends Command
{
    final public const APP_SECRET_LENGTH = 136;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(Random::getString(self::APP_SECRET_LENGTH));

        return self::SUCCESS;
    }
}
