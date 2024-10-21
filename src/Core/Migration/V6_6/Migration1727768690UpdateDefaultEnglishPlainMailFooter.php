<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1727768690UpdateDefaultEnglishPlainMailFooter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1727768690;
    }

    public function update(Connection $connection): void
    {
        $defaultLanguageId = $this->fetchDefaultLanguageId($connection);

        $enPlainFooterFilePath = __DIR__ . '/../Fixtures/mails/defaultMailFooter/en-plain.twig';
        $enPlainFooter = \file_get_contents($enPlainFooterFilePath);
        \assert($enPlainFooter !== false);

        $systemDefaultMailHeaderFooterId = $connection->fetchOne('SELECT `id` FROM `mail_header_footer` WHERE `system_default` = 1');

        $sqlString = 'UPDATE `mail_header_footer_translation` SET `footer_plain` = :footerPlain  WHERE `mail_header_footer_id`= :mailHeaderFooterId AND `language_id` = :enLangId AND `updated_at` IS NULL';
        $connection->executeStatement($sqlString, [
            'footerPlain' => $enPlainFooter,
            'mailHeaderFooterId' => $systemDefaultMailHeaderFooterId,
            'enLangId' => $defaultLanguageId,
        ]);
    }

    private function fetchDefaultLanguageId(Connection $connection): string
    {
        $code = 'en-GB';
        $langId = $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

        if (!$langId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $langId;
    }
}
