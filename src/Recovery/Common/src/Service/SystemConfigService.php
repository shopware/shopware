<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class SystemConfigService
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function set(string $key, $value): void
    {
        $value = json_encode(['_value' => $value], \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);

        $stmt = $this->connection->prepare('SELECT id FROM `system_config` WHERE configuration_key = ?');
        $stmt->execute([$key]);
        $id = $stmt->fetchColumn() ?: null;
        if ($id) {
            $prepareStmt = $this->connection->prepare(
                'UPDATE system_config
                 SET configuration_value = ?
                 WHERE id = ?'
            );
            $prepareStmt->execute([$value, $id]);

            return;
        }

        $id = Uuid::randomBytes();

        $prepareStmt = $this->connection->prepare(
            'INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id)
             VALUES (?, ?, ?, NULL)'
        );
        $prepareStmt->execute([$id, $key, $value]);
    }

    public function get(string $key)
    {
        $stmt = $this->connection->prepare(
            'SELECT configuration_value
             FROM system_config
             WHERE configuration_key = :key AND sales_channel_id IS NULL'
        );
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        if (!$value) {
            return null;
        }

        try {
            $decoded = json_decode($value, true);

            return $decoded['_value'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
