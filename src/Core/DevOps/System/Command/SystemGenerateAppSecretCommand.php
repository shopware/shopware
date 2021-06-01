<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\System\Command;

use Defuse\Crypto\Key;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SystemGenerateAppSecretCommand extends Command
{
    public static $defaultName = 'system:generate-app-secret';

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = Key::createNewRandomKey();

        $output->writeln($key->saveToAsciiSafeString());

        return self::SUCCESS;
    }
}
