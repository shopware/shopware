<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Command;

use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReporter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'framework:store-api:schema-migration-report',
    description: 'Reports Store API component schemas grouped by JSON/PHP migration state.',
)]
class StoreApiSchemaMigrationReportCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly StoreApiSchemaMigrationReporter $reporter,
        private readonly SalesChannelDefinitionInstanceRegistry $definitionRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('outfile', InputArgument::OPTIONAL, 'Path to the output file. "-" writes to stdout.', '-')
            ->addOption('scope', null, InputOption::VALUE_REQUIRED, 'Schema ownership scope to report. Supported values: core, all.', StoreApiSchemaMigrationReporter::SCOPE_CORE)
            ->addOption('pretty', 'p', InputOption::VALUE_NONE, 'Dumps the output in a human-readable form.')
            ->addOption('fail-on-mismatch', null, InputOption::VALUE_NONE, 'Returns a failure code when the report contains migration mismatches.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scope = $input->getOption('scope');
        if (!\is_string($scope) || !\in_array($scope, [StoreApiSchemaMigrationReporter::SCOPE_CORE, StoreApiSchemaMigrationReporter::SCOPE_ALL], true)) {
            $output->writeln('<error>The scope option must be one of: core, all.</error>');

            return self::FAILURE;
        }

        $report = $this->reporter->report($this->definitionRegistry->getDefinitions(), $scope);
        $jsonFlags = $input->getOption('pretty') ? \JSON_PRETTY_PRINT : 0;
        $contents = json_encode($report, $jsonFlags | \JSON_THROW_ON_ERROR);
        \assert(\is_string($contents));

        $contents .= "\n";

        $outFile = $input->getArgument('outfile');
        \assert(\is_string($outFile));

        if ($outFile === '-') {
            $output->write($contents);
        } else {
            file_put_contents($outFile, $contents);
        }

        if ($input->getOption('fail-on-mismatch') && (
            $report['jsonOverridesPhpGeneratedWithoutAllowlist'] !== []
            || $report['phpGeneratedOnlyWithoutAllowlist'] !== []
            || $report['allowlistWithoutJsonOverridesPhpGeneratedSchema'] !== []
            || $report['allowlistWithoutPhpGeneratedOnlySchema'] !== []
        )) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
