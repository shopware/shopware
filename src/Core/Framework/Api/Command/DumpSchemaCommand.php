<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Command;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\ShellPathCompletion;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpSchemaCommand extends Command implements CompletionAwareInterface
{
    /**
     * @var DefinitionService
     */
    private $definitionService;

    public function __construct(DefinitionService $definitionService)
    {
        parent::__construct();

        $this->definitionService = $definitionService;
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
        if ($optionName === 'schema-format') {
            return [
                'simple',
                'openapi3',
            ];
        }

        return [];
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
        if ($argumentName === 'outfile') {
            exit(ShellPathCompletion::PATH_COMPLETION_EXIT_CODE);
        }

        return [];
    }

    protected function configure(): void
    {
        $this->setName('framework:schema')
            ->setDescription('Dumps the api definition to a json file.')
            ->addArgument('outfile', InputArgument::REQUIRED)
            ->addOption(
                'schema-format',
                's',
                InputOption::VALUE_REQUIRED,
                'The format of the dumped definition. Either "simple" or "openapi3".',
                'simple'
            )
            ->addOption('pretty', 'p', InputOption::VALUE_NONE, 'Dumps the output in a human-readable form.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outFile = $input->getArgument('outfile');
        $formatType = $input->getOption('schema-format');

        if ($formatType === 'simple') {
            $definitionContents = $this->definitionService->getSchema();
        } elseif ($formatType === 'openapi3') {
            $definitionContents = $this->definitionService->generate();
        } else {
            throw new \InvalidArgumentException('Invalid "format-type" given. Aborting.');
        }

        $jsonFlags = $input->getOption('pretty') ? JSON_PRETTY_PRINT : 0;

        $output->writeln('Writing definition to file ...');
        file_put_contents($outFile, json_encode($definitionContents, $jsonFlags));
        $output->writeln('Done!');
    }
}
