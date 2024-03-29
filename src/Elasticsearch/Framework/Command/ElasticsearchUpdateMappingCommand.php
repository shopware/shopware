<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'es:mapping:update',
    description: 'Update the Elasticsearch indices mapping',
)]
#[Package('core')]
class ElasticsearchUpdateMappingCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IndexMappingUpdater $indexMappingUpdater,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->indexMappingUpdater->update(Context::createCLIContext());

        return self::SUCCESS;
    }
}
