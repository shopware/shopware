<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Console\OutputFormatTrait;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Feature;
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
    use OutputFormatTrait;

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
        $this->addFormatOption([self::FORMAT_TABLE, self::FORMAT_JSON]);
        /** @deprecated tag:v6.8.0 - Use `--format json` instead */
        $this->addOption(
            'json',
            null,
            InputOption::VALUE_NONE,
            '[DEPRECATED] Use `--format json` instead.'
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
        $io = new ShopwareStyle($input, $output);

        if ($input->getOption('json')) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'The "--json" option of the "dal:validate" command is deprecated and will be removed in v6.8.0. Use "--format json" instead.'
            );
            $input->setOption('format', self::FORMAT_JSON);
        }

        $format = $this->resolveFormat($input, $output, [self::FORMAT_TABLE, self::FORMAT_JSON]);
        if ($format === null) {
            return self::INVALID;
        }

        $asJson = $format === self::FORMAT_JSON;
        $namespaces = $input->getOption('namespaces') ?? [];
        if (!$asJson) {
            $io->title('Data Abstraction Layer Validation');
        }

        $errors = $this->validator->validate();

        // Filter errors by namespaces if provided
        if (!empty($namespaces)) {
            $errors = array_filter(
                $errors,
                static function ($_, $class) use ($namespaces) {
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

        $hasErrors = $errors !== [];
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
