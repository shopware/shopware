<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Feature;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugDumpBusinessEventsCommand extends Command
{
    protected static $defaultName = 'debug:business-events';

    /**
     * @var BusinessEventCollector
     */
    protected $collector;

    public function __construct(BusinessEventCollector $collector)
    {
        parent::__construct();
        $this->collector = $collector;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!Feature::isActive('FEATURE_NEXT_9351')) {
            throw new Feature\FeatureNotActiveException('FEATURE_NEXT_9351');
        }

        $result = $this->collector->collect(Context::createDefaultContext());

        $table = new Table($output);
        $table->setHeaders(['name', 'mail-aware', 'log-aware', 'class']);
        foreach ($result as $definition) {
            $table->addRow([
                $definition->getName(),
                (int) $definition->isMailAware(),
                (int) $definition->isLogAware(),
                $definition->getClass(),
            ]);
        }
        $table->render();

        return 0;
    }
}
