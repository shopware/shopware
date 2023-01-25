<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'debug:business-events',
    description: 'Dumps all business events',
)]
#[Package('business-ops')]
class DebugDumpBusinessEventsCommand extends Command
{
    /**
     * @var BusinessEventCollector
     */
    protected $collector;

    /**
     * @internal
     */
    public function __construct(BusinessEventCollector $collector)
    {
        parent::__construct();
        $this->collector = $collector;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->collector->collect(Context::createDefaultContext());

        $table = new Table($output);
        $table->setHeaders(['name', 'mail-aware', 'log-aware', 'class']);
        foreach ($result as $definition) {
            $table->addRow([
                $definition->getName(),
                (int) $definition->getAware('mailAware'),
                (int) $definition->getAware('logAware'),
                $definition->getClass(),
            ]);
        }
        $table->render();

        return self::SUCCESS;
    }
}
