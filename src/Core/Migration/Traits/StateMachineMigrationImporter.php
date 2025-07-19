<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\StateMachineTranslationDefinition;

#[Package('framework')]
class StateMachineMigrationImporter
{
    use ImportTranslationsTrait;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function importStateMachine(StateMachineMigration $stateMachineMigration): StateMachineMigration
    {
        $stateMachineId = $this->createOrSkipExistingStateMachine($stateMachineMigration);
        $states = $this->createOrSkipExistingStateMachineState($stateMachineMigration, $stateMachineId);
        $transitions = $this->createOrSkipExistingStateMachineStateTransitions($stateMachineMigration, $stateMachineId);

        $initialStateId = $this->updateInitialState($stateMachineMigration, $stateMachineId);

        return new StateMachineMigration(
            $stateMachineMigration->getTechnicalName(),
            $stateMachineMigration->getDe(),
            $stateMachineMigration->getEn(),
            $states,
            $transitions,
            $initialStateId
        );
    }

    private function createOrSkipExistingStateMachine(StateMachineMigration $stateMachineMigration): string
    {
        $id = $this->connection->fetchOne(
            '
            SELECT `id`
            FROM `state_machine`
            WHERE technical_name = :technicalName
            ',
            ['technicalName' => $stateMachineMigration->getTechnicalName()],
        );

        if ($id) {
            return Uuid::fromBytesToHex($id);
        }

        $id = Uuid::randomBytes();

        $this->connection->insert(
            StateMachineDefinition::ENTITY_NAME,
            [
                'id' => $id,
                'technical_name' => $stateMachineMigration->getTechnicalName(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->importTranslation(
            StateMachineTranslationDefinition::ENTITY_NAME,
            new Translations(
                ['state_machine_id' => $id, 'name' => $stateMachineMigration->getDe()],
                ['state_machine_id' => $id, 'name' => $stateMachineMigration->getEn()]
            ),
            $this->connection
        );

        return Uuid::fromBytesToHex($id);
    }

    /**
     * @return list<array{id: string, technicalName: string}>
     */
    private function createOrSkipExistingStateMachineState(
        StateMachineMigration $stateMachineMigration,
        string $stateMachineId
    ): array {
        $inserted = [];

        foreach ($stateMachineMigration->getStates() as $state) {
            if (!\array_key_exists('technicalName', $state)) {
                throw MigrationException::migrationError('Please provide "technicalName" to all states');
            }

            if (!\array_key_exists('de', $state) || !\array_key_exists('en', $state)) {
                throw MigrationException::migrationError('Please provide "de" and "en" translations to all states');
            }

            $technicalName = $state['technicalName'];
            $de = $state['de'];
            $en = $state['en'];

            $id = $this->getStateMachineStateIdByName($stateMachineId, $technicalName);

            if ($id) {
                continue;
            }

            // state does not exist for now
            $id = Uuid::randomBytes();

            $this->connection->insert(
                StateMachineStateDefinition::ENTITY_NAME,
                [
                    'id' => $id,
                    'state_machine_id' => Uuid::fromHexToBytes($stateMachineId),
                    'technical_name' => $technicalName,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );

            $this->importTranslation(
                StateMachineStateTranslationDefinition::ENTITY_NAME,
                new Translations(
                    ['state_machine_state_id' => $id, 'name' => $de],
                    ['state_machine_state_id' => $id, 'name' => $en]
                ),
                $this->connection
            );

            $inserted[] = [
                'id' => Uuid::fromBytesToHex($id),
                'technicalName' => $technicalName,
            ];
        }

        return $inserted;
    }

    /**
     * @return list<array{id: string, actionName: string, fromStateId: string, toStateId: string}>
     */
    private function createOrSkipExistingStateMachineStateTransitions(
        StateMachineMigration $stateMachineMigration,
        string $stateMachineId
    ): array {
        $inserted = [];

        foreach ($stateMachineMigration->getTransitions() as $transition) {
            if (!\array_key_exists('actionName', $transition)) {
                throw MigrationException::migrationError('Please provide "actionName" to all transitions');
            }

            if (!\array_key_exists('from', $transition) || !\array_key_exists('to', $transition)) {
                throw MigrationException::migrationError('Please provide "from" and "to" states to all transitions');
            }

            $actionName = $transition['actionName'];
            $from = $transition['from'];
            $to = $transition['to'];

            $fromStateId = $this->getStateMachineStateIdByName($stateMachineId, $from);
            $toStateId = $this->getStateMachineStateIdByName($stateMachineId, $to);

            if (!$fromStateId) {
                throw MigrationException::migrationError(\sprintf('State with name "%s" not found', $from));
            }

            if (!$toStateId) {
                throw MigrationException::migrationError(\sprintf('State with name "%s" not found', $to));
            }

            $id = $this->connection->fetchOne(
                '
                SELECT `id`
                FROM `state_machine_transition`
                WHERE `state_machine_id` = :stateMachineId
                AND `action_name` = :actionName
                AND `from_state_id` = :fromStateId
                AND `to_state_id` = :toStateId
                ',
                [
                    'stateMachineId' => Uuid::fromHexToBytes($stateMachineId),
                    'actionName' => $actionName,
                    'fromStateId' => Uuid::fromHexToBytes($fromStateId),
                    'toStateId' => Uuid::fromHexToBytes($toStateId),
                ]
            );

            if ($id) {
                continue;
            }

            // transition does not exist for now
            $id = Uuid::randomBytes();

            $this->connection->insert(
                StateMachineTransitionDefinition::ENTITY_NAME,
                [
                    'id' => $id,
                    'state_machine_id' => Uuid::fromHexToBytes($stateMachineId),
                    'action_name' => $actionName,
                    'from_state_id' => Uuid::fromHexToBytes($fromStateId),
                    'to_state_id' => Uuid::fromHexToBytes($toStateId),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );

            $inserted[] = [
                'id' => Uuid::fromBytesToHex($id),
                'actionName' => $actionName,
                'fromStateId' => $fromStateId,
                'toStateId' => $toStateId,
            ];
        }

        return $inserted;
    }

    private function updateInitialState(
        StateMachineMigration $stateMachineMigration,
        string $stateMachineId
    ): ?string {
        if (!$stateMachineMigration->getInitialState()) {
            return null;
        }

        $id = $this->getStateMachineStateIdByName($stateMachineId, $stateMachineMigration->getInitialState());

        if (!$id) {
            throw MigrationException::migrationError(\sprintf('State with name "%s" not found', $stateMachineMigration->getTechnicalName()));
        }

        $this->connection->update(
            StateMachineDefinition::ENTITY_NAME,
            ['initial_state_id' => Uuid::fromHexToBytes($id)],
            ['id' => Uuid::fromHexToBytes($stateMachineId)]
        );

        return $id;
    }

    private function getStateMachineStateIdByName(string $stateMachineId, string $technicalName): ?string
    {
        $id = $this->connection->fetchOne(
            '
            SELECT `id`
            FROM `state_machine_state`
            WHERE `state_machine_id` = :stateMachineId
            AND `technical_name` = :technicalName
            ',
            [
                'stateMachineId' => Uuid::fromHexToBytes($stateMachineId),
                'technicalName' => $technicalName,
            ]
        );

        if (!$id) {
            return null;
        }

        return Uuid::fromBytesToHex($id);
    }
}
