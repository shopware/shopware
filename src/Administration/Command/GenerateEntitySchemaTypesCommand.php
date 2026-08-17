<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Administration\Administration;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_EXTENSION_TOOLING
 *
 * `bin/console` entry for regenerating the Administration entity-schema
 * TypeScript definitions the extension tooling type-checks against — the
 * standard-install counterpart of the `composer admin:generate-entity-schema-types`
 * script, which does not exist in a Composer/Flex shop. Runs the two steps that
 * script runs: dump the entity schema, then convert it to `entity-schema-definition.d.ts`.
 */
#[Package('framework')]
#[AsCommand(
    name: 'administration:generate-entity-schema-types',
    description: 'Generates the Administration entity-schema TypeScript definitions used by the extension tooling.',
)]
class GenerateEntitySchemaTypesCommand extends Command
{
    /**
     * @param string|null $administrationRootPath overrides the bundle-resolved app root; defaults to auto-resolution when null (the production path)
     */
    public function __construct(private readonly ?string $administrationRootPath = null)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $administrationRoot = $this->administrationRoot();
        $schemaFile = $administrationRoot . '/test/_mocks_/entity-schema.json';

        $io->section('Dumping the entity schema');
        $dumpExit = $this->dumpEntitySchema($schemaFile, $output);

        if ($dumpExit !== Command::SUCCESS) {
            return $dumpExit;
        }

        $tsNode = $administrationRoot . '/node_modules/.bin/ts-node';

        if (!is_file($tsNode)) {
            $io->error([
                \sprintf('The Administration\'s Node dependencies are not installed (%s is missing).', $tsNode),
                \sprintf('Run "npm ci" in %s first, then re-run this command.', $administrationRoot),
            ]);

            return Command::FAILURE;
        }

        $io->section('Converting to TypeScript definitions');

        return $this->convertEntitySchema($administrationRoot, $output);
    }

    /**
     * The Administration app root, resolved from the bundle class location so it
     * works in the platform monorepo and in a vendor/ install alike.
     */
    protected function administrationRoot(): string
    {
        return $this->administrationRootPath
            ?? \dirname((string) (new \ReflectionClass(Administration::class))->getFileName())
                . '/Resources/app/administration';
    }

    /**
     * Dumps the entity schema to the given file via the core framework:schema command. Overridable for tests.
     */
    protected function dumpEntitySchema(string $schemaFile, OutputInterface $output): int
    {
        $application = $this->getApplication();

        if ($application === null) {
            return Command::FAILURE;
        }

        return $application->find('framework:schema')->run(
            new ArrayInput([
                'outfile' => $schemaFile,
                '--schema-format' => 'entity-schema',
            ]),
            $output,
        );
    }

    /**
     * Converts the dumped schema to entity-schema-definition.d.ts via the ts-node converter. Overridable for tests.
     */
    protected function convertEntitySchema(string $administrationRoot, OutputInterface $output): int
    {
        $process = new Process(
            [
                $administrationRoot . '/node_modules/.bin/ts-node',
                '--transpileOnly',
                $administrationRoot . '/scripts/entitySchemaConverter/convert-schema.ts',
            ],
            $administrationRoot,
            null,
            null,
            null,
        );

        return $process->run(static function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });
    }
}
