<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1615452749ChangeDefaultMailSendAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1615452749;
    }

    public function update(Connection $connection): void
    {
        $basicMails = $connection->fetchAllAssociative(
            'SELECT id, configuration_value FROM system_config WHERE configuration_key = :key',
            [
                'key' => 'core.basicInformation.email',
            ]
        );

        foreach ($basicMails as $basicMail) {
            if (isset($basicMail['configuration_value']) && \is_string($basicMail['configuration_value'])) {
                $configValue = json_decode($basicMail['configuration_value'], true, 512, \JSON_THROW_ON_ERROR);
                if (isset($configValue['_value']) && $configValue['_value'] === 'doNotReply@localhost') {
                    $connection->executeStatement(
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
