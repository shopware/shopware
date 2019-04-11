<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\BinaryType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DebugViewGenerator extends Command
{
    private const PREFIX = 'debug_';

    private const DROP_VIEW_TEMPLATE = <<<EOD
DROP VIEW IF EXISTS %s;
EOD;

    private const CREATE_VIEW_TANPLATE = <<<EOD
CREATE VIEW %s AS (SELECT %s FROM %s);
EOD;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this->setDescription(sprintf('Generate "%s*" views with readable uuids for development and debugging', self::PREFIX));
        $this->setName('dataabstractionlayer:generate-debug-views');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Data Abstraction Layer Development view generator');

        $tableNames = $this->connection->getSchemaManager()->listTableNames();

        $io->progressStart(count($tableNames));
        foreach ($tableNames as $tableName) {
            $viewColumns = $this->getColumns($tableName);
            $this->updateDatabaseView($tableName, $viewColumns);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $io->success('Done creating ' . count($tableNames) . ' views');
    }

    protected function updateDatabaseView($tableName, array $viewColumns): void
    {
        $viewName = $this->connection
            ->quoteIdentifier(self::PREFIX . $tableName);

        $dropViewSql = sprintf(self::DROP_VIEW_TEMPLATE, $viewName);
        $createViewSql = sprintf(
            self::CREATE_VIEW_TANPLATE,
            $viewName,
            implode(', ', $viewColumns),
            $this->connection->quoteIdentifier($tableName)
        );

        $this->connection->exec($dropViewSql);
        $this->connection->exec($createViewSql);
    }

    private function getColumns(string $tableName): array
    {
        $columns = $this->connection->getSchemaManager()->listTableColumns($tableName);

        $viewColumns = [];

        foreach ($columns as $column) {
            if ($column->getType() instanceof BinaryType) {
                $viewColumns[] = "LOWER(HEX({$column->getName()})) as {$column->getName()}";
            } else {
                $viewColumns[] = $this->connection->quoteIdentifier($column->getName());
            }
        }

        return $viewColumns;
    }
}
