<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\ORM\DefinitionValidator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OrmValidateCommand extends ContainerAwareCommand
{
    /**
     * @var DefinitionValidator
     */
    private $validator;

    public function __construct(DefinitionValidator $validator)
    {
        parent::__construct(null);
        $this->validator = $validator;
    }

    protected function configure()
    {
        $this->setName('orm:validate');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Orm validator');

        $errors = 0;
        if ($io->isVerbose()) {
            $io->title('Checking for notices in entity definitions');
            $errors += $this->runNotices($io);
        }

        $io->title('Checking for errors in entity definitions');
        $errors += $this->runErrors($io);

        return $errors;
    }

    protected function runNotices(SymfonyStyle $io): int
    {
        $notices = $this->validator->getNotices($this->getContainer());

        $count = 0;
        foreach ($notices as $definition => $matches) {
            $count += count($matches);
            $io->section($definition);
            $io->listing($matches);
            $io->newLine();
        }

        if ($count <= 0) {
            $io->success('No notices found');
        } else {
            $io->note(sprintf('Found %s notices in %s entities', $count, count($notices)));
        }

        return $count;
    }

    protected function runErrors(SymfonyStyle $io): int
    {
        $violations = $this->validator->validate();

        $count = 0;
        foreach ($violations as $definition => $matches) {
            $count += count($matches);
            $io->section($definition);
            $io->listing($matches);
            $io->newLine();
        }

        if ($count <= 0) {
            $io->success('No errors found');
        } else {
            $io->error(sprintf('Found %s errors in %s entities', $count, count($violations)));
        }

        return $count;
    }
}
