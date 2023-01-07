<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package core
 */
#[AsCommand(
    name: 'es:create:alias',
    description: 'Create the elasticsearch alias',
)]
class ElasticsearchCreateAliasCommand extends Command
{
    private CreateAliasTaskHandler $handler;

    /**
     * @internal
     */
    public function __construct(CreateAliasTaskHandler $handler)
    {
        parent::__construct();
        $this->handler = $handler;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Dev command to create alias immediately');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->handler->run();

        return self::SUCCESS;
    }
}
