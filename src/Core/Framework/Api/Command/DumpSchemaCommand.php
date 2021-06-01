<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Command;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpSchemaCommand extends Command
{
    protected static $defaultName = 'framework:schema';

    /**
     * @var DefinitionService
     */
    private $definitionService;

    public function __construct(DefinitionService $definitionService)
    {
        parent::__construct();

        $this->definitionService = $definitionService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Dumps the api definition to a json file.')
            ->addArgument('outfile', InputArgument::REQUIRED)
            ->addOption(
                'schema-format',
                's',
                InputOption::VALUE_REQUIRED,
                'The format of the dumped definition. Either "simple", "openapi3" or "entity-schema.',
                'simple'
            )
            ->addOption('pretty', 'p', InputOption::VALUE_NONE, 'Dumps the output in a human-readable form.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outFile = $input->getArgument('outfile');
        $formatType = $input->getOption('schema-format');

        if ($formatType === 'simple') {
            $definitionContents = $this->definitionService->getSchema();
        } elseif ($formatType === 'openapi3') {
            $definitionContents = $this->definitionService->generate();
        } elseif ($formatType === 'entity-schema') {
            $definitionContents = $this->definitionService->getSchema(EntitySchemaGenerator::FORMAT, DefinitionService::API);
        } else {
            throw new \InvalidArgumentException('Invalid "format-type" given. Aborting.');
        }

        $jsonFlags = $input->getOption('pretty') ? \JSON_PRETTY_PRINT : 0;

        $output->writeln('Writing definition to file ...');
        file_put_contents($outFile, json_encode($definitionContents, $jsonFlags));
        $output->writeln('Done!');

        return self::SUCCESS;
    }
}
