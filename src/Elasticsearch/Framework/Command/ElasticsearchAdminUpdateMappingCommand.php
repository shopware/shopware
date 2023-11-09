<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'es:admin:mapping:update',
    description: 'Update the Elasticsearch indices mapping',
)]
#[Package('core')]
class ElasticsearchAdminUpdateMappingCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly AdminSearchRegistry $registry)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->registry->updateMappings();

        $io->success('Updated mapping for admin indices');

        return self::SUCCESS;
    }
}
