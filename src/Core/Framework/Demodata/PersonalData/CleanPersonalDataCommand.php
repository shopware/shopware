<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\PersonalData;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanPersonalDataCommand extends Command
{
    protected const VALID_TYPES = [
        self::TYPE_GUESTS,
        self::TYPE_CARTS,
    ];

    protected const TYPE_GUESTS = 'guests';
    protected const TYPE_CARTS = 'carts';

    protected static $defaultName = 'database:clean-personal-data';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $customerRepository
    ) {
        parent::__construct();
        $this->connection = $connection;
        $this->customerRepository = $customerRepository;
    }

    protected function configure(): void
    {
        $this->addArgument('type', InputArgument::OPTIONAL)
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'An optional numeric value for removing guests without orders or canceled carts after the number of days'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Cleans any possible personal data: guests without orders and canceled carts'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        $all = $input->getOption('all');

        if (!$all && !\in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                'Please add the argument "guests" to remove guests without orders or the argument "carts" to remove canceled carts. Use --all to clean both.'
            );
        }

        $days = (int) $input->getOption('days');

        if ($all || $type === self::TYPE_GUESTS) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('guest', true));
            $criteria->addFilter(new EqualsFilter('orderCustomers.id', null));
            $criteria->addFilter(
                new RangeFilter(
                    'createdAt',
                    [
                        RangeFilter::LTE => (new \DateTime())->modify(-abs($days) . ' Day')
                            ->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                )
            );

            $ids = $this->customerRepository
                ->searchIds($criteria, Context::createDefaultContext())
                ->getIds();

            if ($ids) {
                $ids = array_map(function ($id) {
                    return ['id' => $id];
                }, $ids);

                $this->customerRepository->delete($ids, Context::createDefaultContext());
            }
        }

        if ($all || $type === self::TYPE_CARTS) {
            $this->connection->executeUpdate(
                'DELETE FROM cart
                WHERE DATE(created_at) <= (DATE_SUB(CURDATE(), INTERVAL :days DAY))',
                ['days' => $days]
            );
        }

        $output->writeln('Personal data for ' . ($all ? implode(' and ', self::VALID_TYPES) : $type) . ' successfully cleaned!');

        return self::SUCCESS;
    }
}
