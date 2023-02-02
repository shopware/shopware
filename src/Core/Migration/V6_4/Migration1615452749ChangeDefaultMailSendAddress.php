<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1615452749ChangeDefaultMailSendAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1615452749;
    }

    public function update(Connection $connection): void
    {
        $basicMails = $connection->fetchAll(
            'SELECT id, configuration_value FROM system_config WHERE configuration_key = :key',
            [
                'key' => 'core.basicInformation.email',
            ]
        );

        foreach ($basicMails as $basicMail) {
            if (isset($basicMail['configuration_value']) && \is_string($basicMail['configuration_value'])) {
                $configValue = json_decode($basicMail['configuration_value'], true);
                if (isset($configValue['_value']) && $configValue['_value'] === 'doNotReply@localhost') {
                    $connection->executeUpdate(
                        'UPDATE system_config SET configuration_value = :defaultMail WHERE id = :id',
                        [
                            'defaultMail' => '{"_value": "doNotReply@localhost.com"}',
                            'id' => $basicMail['id'],
                        ]
                    );
                }
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
