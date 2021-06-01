<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ElasticsearchCreateAliasCommand extends Command
{
    protected static $defaultName = 'es:create:alias';

    private CreateAliasTaskHandler $handler;

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
