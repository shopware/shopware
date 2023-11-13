<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dal:validate',
    description: 'Validates the DAL definitions',
)]
#[Package('core')]
class DataAbstractionLayerValidateCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly DefinitionValidator $validator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $io->title('Data Abstraction Layer Validation');

        $errors = 0;
        if ($io->isVerbose()) {
            $io->title('Checking for notices in entity definitions');
            $errors += $this->runNotices($io);
        }

        $io->title('Checking for errors in entity definitions');
        $errors += $this->runErrors($io);

        return $errors;
    }

    private function runNotices(SymfonyStyle $io): int
    {
        $notices = $this->validator->getNotices();

        $count = 0;
        foreach ($notices as $definition => $matches) {
            $count += is_countable($matches) ? \count($matches) : 0;
            $io->section($definition);
            $io->listing($matches);
            $io->newLine();
        }

        if ($count <= 0) {
            $io->success('No notices found');
        } else {
            $io->note(sprintf('Found %d notices in %d entities', $count, \count($notices)));
        }

        return $count;
    }

    private function runErrors(SymfonyStyle $io): int
    {
        $violations = $this->validator->validate();

        $count = 0;
        foreach ($violations as $definition => $matches) {
            $count += is_countable($matches) ? \count($matches) : 0;
            $io->section($definition);
            $io->listing($matches);
            $io->newLine();
        }

        if ($count <= 0) {
            $io->success('No errors found');
        } else {
            $io->error(sprintf('Found %d errors in %d entities', $count, \count($violations)));
        }

        return $count;
    }
}
