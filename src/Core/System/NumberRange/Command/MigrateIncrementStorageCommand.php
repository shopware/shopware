<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateIncrementStorageCommand extends Command
{
    protected static $defaultName = 'number-range:migrate';

    private IncrementStorageRegistry $registry;

    /**
     * @internal
     */
    public function __construct(IncrementStorageRegistry $registry)
    {
        $this->registry = $registry;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Migrates the current states of the number ranges from the given storage to the given storage. Note that if this command runs during load on the system it may be possible that the same number is generated twice.')
            ->addArgument('from', InputArgument::REQUIRED, 'The storage name from which you want to migrate.')
            ->addArgument('to', InputArgument::REQUIRED, 'The storage name to which you want to migrate to.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $io->warning('Migrating the number range increments during load may lead to duplicate numbers being generated.');

        if (!$io->confirm('Are you sure you want to continue?')) {
            $io->error('Aborting due to user input.');

            return self::FAILURE;
        }

        $from = $input->getArgument('from');
        $to = $input->getArgument('to');

        $this->registry->migrate(
            $from,
            $to
        );

        $io->success(\sprintf('Successfully migrated number range increments from "%s" to "%s"', $from, $to));

        return self::SUCCESS;
    }
}
