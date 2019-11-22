<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1571660203FixOrderDeliveryStateNames extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1571660203;
    }

    public function update(Connection $connection): void
    {
        foreach ($this->getMailTemplatesMapping() as $technicalName => $mailTemplate) {
            $sql = <<<SQL
            UPDATE `mail_template_type_translation` SET `name` = :name 
                WHERE `mail_template_type_id` = (SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName) 
                  AND `language_id` = :lang
SQL;

            $connection->executeQuery($sql, ['name' => $mailTemplate['name'], 'technicalName' => $technicalName, 'lang' => $this->getLanguageIdByLocale($connection, 'en-GB')]);

            $sql = <<<SQL
            UPDATE `mail_template_type_translation` SET `name` = :name 
                WHERE `mail_template_type_id` = (SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName) 
                  AND `language_id` = :lang
SQL;

            $connection->executeQuery($sql, ['name' => $mailTemplate['nameDe'], 'technicalName' => $technicalName, 'lang' => $this->getLanguageIdByLocale($connection, 'de-DE')]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getMailTemplatesMapping()
    {
        return [
            'state_enter.order_delivery.state.returned_partially' => [
                'name' => 'Enter delivery state: Open',
                'nameDe' => 'Eintritt Lieferstatus: Offen',
            ],
            'state_enter.order_delivery.state.shipped_partially' => [
                'name' => 'Enter delivery state: Shipped (partially)',
                'nameDe' => 'Eintritt Lieferstatus: Teilweise versandt',
            ],
            'state_enter.order_delivery.state.returned' => [
                'name' => 'Enter delivery state: Returned',
                'nameDe' => 'Eintritt Lieferstatus: Retour',
            ],
            'state_enter.order_delivery.state.shipped' => [
                'name' => 'Enter delivery state: Shipped',
                'nameDe' => 'Eintritt Lieferstatus: Versandt',
            ],
            'state_enter.order_delivery.state.cancelled' => [
                'name' => 'Enter delivery state: Cancelled',
                'nameDe' => 'Eintritt Lieferstatus: Abgebrochen',
            ],
        ];
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): string
    {
        $sql = <<<SQL
SELECT `language`.`id` 
FROM `language` 
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchColumn();
        if (!$languageId) {
            throw new \RuntimeException(sprintf('Language for locale "%s" not found.', $locale));
        }

        return $languageId;
    }
}
