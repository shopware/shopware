<?php declare(strict_types=1);

namespace Shopware\Administration\Dashboard;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
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
    public function load(Context $context, string $since, bool $paid, string $timezone = 'UTC'): array
    {
        $query = $this->connection->createQueryBuilder();

        $accessor = '`order`.order_date_time';

        if ($this->timeZoneSupportEnabled) {
            $accessor = 'IFNULL(CONVERT_TZ(' . $accessor . ', :dbtimezone, :timezone), ' . $accessor . ')';

            $query->setParameter('dbtimezone', '+00:00');
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
        $query->leftJoin('`order`', 'currency', 'currency', '`order`.currency_factor is null AND currency.id = `order`.currency_id');
        $query->andWhere('`order`.order_date_time >= :since');
        $query->andWhere('`order`.version_id = :version');

        $query->groupBy('`date`');

        if ($paid) {
            $paidId = $this->connection->fetchOne('
                SELECT state.id FROM state_machine_state state
                INNER JOIN state_machine ON state.state_machine_id = state_machine.id
                WHERE state_machine.technical_name = :state_machine AND state.technical_name = :state
            ', [
                'state_machine' => OrderTransactionStates::STATE_MACHINE,
                'state' => OrderTransactionStates::STATE_PAID,
            ]);

            $query->innerJoin('`order`', 'order_transaction', 'transactions', 'transactions.order_id = `order`.id AND transactions.version_id = `order`.version_id AND transactions.state_id = :paidId');
            $query->setParameter('paidId', $paidId);
        }

        $query->setParameter('since', $since);
        $query->setParameter('version', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));

        $query->orderBy('`date`', 'ASC');

        $data = $query->executeQuery()->fetchAllAssociative();

        $rounding = $context->getRounding();

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
