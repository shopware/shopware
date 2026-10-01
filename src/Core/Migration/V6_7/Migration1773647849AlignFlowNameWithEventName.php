<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\V6_5\Migration1689257577AddMissingTransactionMailFlow;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1773647849AlignFlowNameWithEventName extends MigrationStep
{
    final public const FLOW_TRANSLATION_MAPPING = [
        'state_enter.order_transaction.state.authorized' => [
            'old' => Migration1689257577AddMissingTransactionMailFlow::AUTHORIZED_FLOW,
            'new' => 'Payment enters status authorized',
        ],
        'state_enter.order_transaction.state.chargeback' => [
            'old' => Migration1689257577AddMissingTransactionMailFlow::CHARGEBACK_FLOW,
            'new' => 'Payment enters status chargeback',
        ],
        'state_enter.order_transaction.state.unconfirmed' => [
            'old' => Migration1689257577AddMissingTransactionMailFlow::UNCONFIRMED_FLOW,
            'new' => 'Payment enters status unconfirmed',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1773647849;
    }

    public function update(Connection $connection): void
    {
        foreach (self::FLOW_TRANSLATION_MAPPING as $eventName => $data) {
            $connection->update(
                'flow',
                ['name' => $data['new']],
                ['event_name' => $eventName, 'name' => $data['old']]
            );
        }

        $this->registerIndexer($connection, 'flow.indexer');
    }
}
