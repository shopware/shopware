<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DataAbstractionLayerValidateCommand extends Command
{
    protected static $defaultName = 'dal:validate';

    /**
     * @var DefinitionValidator
     */
    private $validator;

    public function __construct(DefinitionValidator $validator)
    {
        parent::__construct();
        $this->validator = $validator;
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
            $count += \count($matches);
            $io->section($definition);
            $io->listing($matches);
            $io->newLine();
        }

        if ($count <= 0) {
            $io->success('No notices found');
        } else {
            $io->note(sprintf('Found %s notices in %s entities', $count, \count($notices)));
        }

        return $count;
    }

    private function runErrors(SymfonyStyle $io): int
    {
        $violations = $this->validator->validate();

        $count = 0;
        foreach ($violations as $definition => $matches) {
            $count += \count($matches);
            $io->section($definition);
            $io->listing($matches);
            $io->newLine();
        }

        if ($count <= 0) {
            $io->success('No errors found');
        } else {
            $io->error(sprintf('Found %s errors in %s entities', $count, \count($violations)));
        }

        return $count;
    }
}
