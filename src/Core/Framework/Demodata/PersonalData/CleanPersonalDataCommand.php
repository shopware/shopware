<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\PersonalData;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Demodata\DemodataException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'database:clean-personal-data',
    description: 'Cleans personal data from the database',
)]
class CleanPersonalDataCommand extends Command
{
    protected const VALID_TYPES = [
        self::TYPE_GUESTS,
        self::TYPE_CARTS,
    ];

    protected const TYPE_GUESTS = 'guests';
    protected const TYPE_CARTS = 'carts';

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $customerRepository,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Type of personal data to clean (guests or carts)')]
        ?string $type = null,
        #[Option(description: 'An optional numeric value for removing guests without orders or canceled carts after the number of days', shortcut: 'd')]
        ?int $days = null,
        #[Option(description: 'Cleans any possible personal data: guests without orders and canceled carts', shortcut: 'a')]
        bool $all = false,
    ): int {
        $types = array_filter($all ? self::VALID_TYPES : [$type]);
        if ($types === [] || \array_diff($types, self::VALID_TYPES) !== []) {
            throw DemodataException::invalidArgument(
                'Please add the argument "type=guests" to remove guests without orders or the argument "type=carts" to remove canceled carts. Use --all to clean both.'
            );
        }

        $days ??= 0;
        if (\in_array(self::TYPE_GUESTS, $types, true)) {
            $criteria = new Criteria();
            $criteria
                ->addFilter(new EqualsFilter('guest', true))
                ->addFilter(new EqualsFilter('orderCustomers.id', null))
                ->addFilter(new RangeFilter('createdAt', [
                    RangeFilter::LTE => $this->clock->now()->modify(-abs($days) . ' Day')
                        ->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]));

            $context = Context::createCLIContext();
            $ids = $this->customerRepository->searchIds($criteria, $context)->getPrimaryKeyData();

            if ($ids !== []) {
                $this->customerRepository->delete($ids, $context);
            }

            $io->success('Personal data for guests successfully cleaned!');
        }

        if (\in_array(self::TYPE_CARTS, $types, true)) {
            $this->connection->executeStatement(
                'DELETE FROM cart
                WHERE DATE(created_at) <= (DATE_SUB(CURDATE(), INTERVAL :days DAY))',
                ['days' => $days]
            );

            $io->success('Personal data for carts successfully cleaned!');
        }

        return self::SUCCESS;
    }
}
