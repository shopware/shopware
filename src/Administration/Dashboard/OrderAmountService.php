<?php declare(strict_types=1);

namespace Shopware\Administration\Dashboard;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class OrderAmountService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CashRounding $rounding,
        private readonly bool $timeZoneSupportEnabled,
    ) {
    }

    /**
     * @return list<array{date:string, amount:float, count:int}>
     */
    public function load(string $since, bool $paid, string $timezone = 'UTC'): array
    {
        $rounding = (int) $this->connection->fetchOne(
            'SELECT currency.total_rounding FROM currency WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::CURRENCY)]
        );

        $rounding = json_decode((string) $rounding, true);
        $rounding = !empty($rounding) ? $rounding : ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true];

        $rounding = new CashRoundingConfig(
            $rounding['decimals'],
            $rounding['interval'],
            $rounding['roundForNet']
        );

        $query = $this->connection->createQueryBuilder();

        $accessor = '`order`.order_date_time';

        if ($this->timeZoneSupportEnabled) {
            $accessor = 'CONVERT_TZ(`order`.order_date_time, \'+00:00\', :timezone)';

            $query->setParameter('timezone', $timezone);
        }

        $query->select(
            'DATE_FORMAT(' . $accessor . ', \'%Y-%m-%d\') as `date`',
            'COUNT(`order`.id) as `count`',
            'SUM(
                `order`.amount_total / IFNULL(`order`.currency_factor, currency.factor)
            ) as `amount`',
        );

        $query->from('`order`');
        $query->leftJoin('`order`', 'currency', 'currency', 'currency.id = `order`.currency_id');
        $query->andWhere('`order`.order_date_time >= :since');
        $query->andWhere('`order`.version_id = :version');

        $query->groupBy('DATE_FORMAT(' . $accessor . ', \'%Y-%m-%d\')');

        if ($paid) {
            $query->innerJoin('`order`', 'order_transaction', 'transactions', 'transactions.order_id = `order`.id AND `transactions`.`version_id` = `order`.version_id');
            $query->innerJoin('transactions', 'state_machine_state', 'state', 'transactions.state_id = state.id');
            $query->andWhere('state.technical_name = :paid');
            $query->setParameter('paid', OrderTransactionStates::STATE_PAID);
        }

        $query->setParameter('since', $since);
        $query->setParameter('version', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));

        $query->orderBy('order_date_time', 'ASC');

        $data = $query->executeQuery()->fetchAllAssociative();

        $mapped = [];
        foreach ($data as $row) {
            $mapped[] = [
                'date' => $row['date'],
                'count' => (int) $row['count'],
                'amount' => $this->rounding->cashRound((float) $row['amount'], $rounding),
            ];
        }

        return $mapped;
    }
}
