<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

class SqlValueGeneratorConnector extends ValueGeneratorConnector
{
    protected $connectorId = 'standard_value_generator_connector';

    public function pullState()
    {
        $stmt = $this->connection->executeQuery(
            "SELECT `last_value` FROM `number_range_state` WHERE HEX(`number_range_id`) = '52352352352300000000000000000000'"
        );
        $lastNumber = $stmt->fetchColumn();

        $nextNumber = $this->valueGenerator->incrementBy($lastNumber);

        $this->connection->executeQuery(
            'UPDATE `number_range_state` SET `last_value` = :value WHERE HEX(`number_range_id`) = :id',
            [
                'value' => $nextNumber,
                'id' => '52352352352300000000000000000000',
            ]
        );

        return $lastNumber;
    }
}
