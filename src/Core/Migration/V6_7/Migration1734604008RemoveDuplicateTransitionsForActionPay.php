<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1734604008RemoveDuplicateTransitionsForActionPay extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1734604008;
    }

    public function update(Connection $connection): void
    {
        $duplicateTransitionIds = $this->findDuplicateTransitions($connection);

        if ($duplicateTransitionIds !== null) {
            $this->removeDuplicateTransitions($connection, $duplicateTransitionIds);
        }
    }

    protected function findDuplicateTransitions(Connection $connection): ?array
    {
        $sql = <<<SQL
SELECT
	smt.id
FROM
	state_machine_transition smt
JOIN
	state_machine_state sms_from
	ON (smt.from_state_id = sms_from.id)
JOIN
	state_machine_state sms_to
	ON (smt.to_state_id = sms_to.id)
WHERE
	# Check combination of from_state's and to_state's technical_name
	CONCAT(sms_from.technical_name, '_', sms_to.technical_name) IN (
		SELECT
			CONCAT(sms_from_inner.technical_name, '_', sms_to_inner.technical_name)
		FROM
			state_machine_transition smt_inner
		JOIN
			state_machine_state sms_from_inner
			ON (smt_inner.from_state_id = sms_from_inner.id)
		JOIN
			state_machine_state sms_to_inner
			ON (smt_inner.to_state_id = sms_to_inner.id)
		# Get all transitions with actions 'pay', 'paid', 'pay_partially' or 'paid_partially'
		WHERE
			smt_inner.action_name IN ('pay', 'paid', 'pay_partially', 'paid_partially')
		# Group by combination of from state's and to_state's technical name
		GROUP BY
			sms_from_inner.technical_name, sms_to_inner.technical_name
		# Only get those transitions that have more than 1 entry.
		# These are transitions, that have an entry for action_name = 'pay'/'paid' resp. 'pay_partially'/'paid_partially'.
		# As we have an transition entry for action_name = 'paid' resp. 'paid_partially'
		# we can safely delete the entry for action_name = 'pay' resp. 'pay_partially'.
		HAVING COUNT(*) > 1
	)
	AND smt.action_name IN ('pay', 'pay_partially');
SQL;

        $result = $connection->executeQuery($sql)->fetchFirstColumn();

        return $result ?: null;
    }

    protected function removeDuplicateTransitions(Connection $connection, array $duplicateTransitionIds): void
    {
        $connection->executeStatement('DELETE FROM state_machine_transition WHERE id IN (:duplicateTransitionIds)',
            ['duplicateTransitionIds' => $duplicateTransitionIds],
            ['duplicateTransitionIds' => ArrayParameterType::BINARY]
        );
    }
}
