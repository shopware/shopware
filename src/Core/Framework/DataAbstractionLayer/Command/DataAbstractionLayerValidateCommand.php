<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dal:validate',
    description: 'Validates the DAL definitions',
)]
#[Package('framework')]
class DataAbstractionLayerValidateCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly DefinitionValidator $validator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'json',
            null,
            InputOption::VALUE_NONE,
            'Output as JSON'
        );
        $this->addOption(
            'namespaces',
            null,
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Only output errors for these PHP namespaces (comma-separated or repeatable)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $asJson = $input->getOption('json');
        $namespaces = $input->getOption('namespaces') ?? [];
        $io = new ShopwareStyle($input, $output);
        if (!$asJson) {
            $io->title('Data Abstraction Layer Validation');
        }

        $errors = $this->validator->validate();

        // Filter errors by namespaces if provided
        if (!empty($namespaces)) {
            $errors = array_filter(
                $errors,
                function ($_, $class) use ($namespaces) {
                    foreach ($namespaces as $ns) {
                        if (str_starts_with($class, (string) $ns)) {
                            return true;
                        }
                    }

                    return false;
                },
                \ARRAY_FILTER_USE_BOTH
            );
        }

        $hasErrors = \count($errors) > 0;
        if ($asJson) {
            if ($hasErrors) {
                $io->write(json_encode($errors, \JSON_THROW_ON_ERROR));
            }
        } else {
            $io->title('Checking for errors in entity definitions');
            $this->printErrors($io, $errors);
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param array<class-string<EntityDefinition|DefinitionInstanceRegistry>, list<string>> $errors
     */
    private function printErrors(SymfonyStyle $io, array $errors): void
    {
        $count = 0;
        foreach ($errors as $definition => $matches) {
            $count += is_countable($matches) ? \count($matches) : 0;
            $io->section($definition);
            $io->listing($matches);
            $io->newLine();
        }

        if ($count <= 0) {
            $io->success('No errors found');
        } else {
            $io->error(\sprintf('Found %d errors in %d entities', $count, \count($errors)));
        }
    }
}
