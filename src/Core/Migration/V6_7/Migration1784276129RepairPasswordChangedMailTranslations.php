<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1784276129RepairPasswordChangedMailTranslations extends MigrationStep
{
    private const WRONG_GERMAN_NAME = 'Kunden-Password geändert';

    private const CORRECT_GERMAN_NAME = 'Kunden-Passwort geändert';

    public function getCreationTimestamp(): int
    {
        return 1784276129;
    }

    public function update(Connection $connection): void
    {
        // The broken literal only ever originated from Migration1763377570, so every
        // occurrence is incorrect - including copies in other languages or templates.
        $connection->executeStatement(
            'UPDATE `mail_template_type_translation` SET `name` = :correctName WHERE `name` = :wrongName',
            [
                'wrongName' => self::WRONG_GERMAN_NAME,
                'correctName' => self::CORRECT_GERMAN_NAME,
            ],
        );

        $connection->executeStatement(
            'UPDATE `mail_template_translation` SET `subject` = :correctSubject WHERE `subject` = :wrongSubject',
            [
                'wrongSubject' => self::WRONG_GERMAN_NAME,
                'correctSubject' => self::CORRECT_GERMAN_NAME,
            ],
        );
    }
}
