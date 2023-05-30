<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1571660203FixOrderDeliveryStateNames extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1571660203;
    }

    public function update(Connection $connection): void
    {
        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        foreach ($this->getMailTemplatesMapping() as $technicalName => $mailTemplate) {
            if ($defaultLangId !== $deLangId) {
                $sql = <<<'SQL'
                UPDATE `mail_template_type_translation` SET `name` = :name
                    WHERE `mail_template_type_id` = (SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName)
                      AND `language_id` = :lang
SQL;

                $connection->executeStatement($sql, ['name' => $mailTemplate['name'], 'technicalName' => $technicalName, 'lang' => $defaultLangId]);
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $sql = <<<'SQL'
                UPDATE `mail_template_type_translation` SET `name` = :name
                    WHERE `mail_template_type_id` = (SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName)
                      AND `language_id` = :lang
SQL;

                $connection->executeStatement($sql, ['name' => $mailTemplate['name'], 'technicalName' => $technicalName, 'lang' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
            }

            if ($deLangId) {
                $sql = <<<'SQL'
                UPDATE `mail_template_type_translation` SET `name` = :name
                    WHERE `mail_template_type_id` = (SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName)
                      AND `language_id` = :lang
SQL;

                $connection->executeStatement($sql, ['name' => $mailTemplate['nameDe'], 'technicalName' => $technicalName, 'lang' => $deLangId]);
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @return array<string, array{name: string, nameDe: string}>
     */
    private function getMailTemplatesMapping(): array
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

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
        if (!$languageId && $locale !== 'en-GB') {
            return null;
        }

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }
}
