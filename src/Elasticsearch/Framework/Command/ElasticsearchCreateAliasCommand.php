<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'es:create:alias',
    description: 'Create the elasticsearch alias',
)]
#[Package('core')]
class ElasticsearchCreateAliasCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly CreateAliasTaskHandler $handler)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->handler->run();

        return self::SUCCESS;
    }
}
