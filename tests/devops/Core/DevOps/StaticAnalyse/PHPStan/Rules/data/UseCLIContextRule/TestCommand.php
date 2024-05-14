<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\UseCLIContextRule;

use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class TestCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Context::createDefaultContext();

        return 0;
    }
}
