<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
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

    private const GERMAN_LOCALE_PREFIX = 'de-%';

    public function getCreationTimestamp(): int
    {
        return 1784276129;
    }

    public function update(Connection $connection): void
    {
        // Migration1763377570 picked its German target language via the de-DE translation code,
        // so the repair matches over the same relation instead of the language's own locale.
        // The de-% prefix also covers broken values copied into other German-variant languages.
        $connection->executeStatement(
            'UPDATE `mail_template_type_translation` AS `translation`
             INNER JOIN `mail_template_type` AS `type` ON `type`.`id` = `translation`.`mail_template_type_id`
             INNER JOIN `language` ON `language`.`id` = `translation`.`language_id`
             INNER JOIN `locale` ON `locale`.`id` = `language`.`translation_code_id`
             SET `translation`.`name` = :correctName
             WHERE `type`.`technical_name` = :technicalName
                 AND `locale`.`code` LIKE :germanLocalePrefix
                 AND `translation`.`name` = :wrongName',
            [
                'technicalName' => CustomerPasswordChangedEvent::EVENT_NAME,
                'germanLocalePrefix' => self::GERMAN_LOCALE_PREFIX,
                'wrongName' => self::WRONG_GERMAN_NAME,
                'correctName' => self::CORRECT_GERMAN_NAME,
            ],
        );

        $connection->executeStatement(
            'UPDATE `mail_template_translation` AS `translation`
             INNER JOIN `mail_template` AS `template` ON `template`.`id` = `translation`.`mail_template_id`
             INNER JOIN `mail_template_type` AS `type` ON `type`.`id` = `template`.`mail_template_type_id`
             INNER JOIN `language` ON `language`.`id` = `translation`.`language_id`
             INNER JOIN `locale` ON `locale`.`id` = `language`.`translation_code_id`
             SET `translation`.`subject` = :correctSubject
             WHERE `type`.`technical_name` = :technicalName
                 AND `locale`.`code` LIKE :germanLocalePrefix
                 AND `translation`.`subject` = :wrongSubject',
            [
                'technicalName' => CustomerPasswordChangedEvent::EVENT_NAME,
                'germanLocalePrefix' => self::GERMAN_LOCALE_PREFIX,
                'wrongSubject' => self::WRONG_GERMAN_NAME,
                'correctSubject' => self::CORRECT_GERMAN_NAME,
            ],
        );
    }
}
