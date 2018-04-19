<?php

class Migrations_Migration615 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $statement = $this->connection->query("SELECT version FROM `s_schema_version` WHERE version = 506 LIMIT 1");
        $version = $statement->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($version)) {
            return;
        }

        $this->addSql("ALTER TABLE `s_core_optin` ADD `type` VARCHAR( 255 ) NULL DEFAULT NULL AFTER `id`");

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_config_mails` (`id`, `stateId`, `name`, `frommail`, `fromname`, `subject`, `content`, `contentHTML`, `ishtml`, `attachment`, `mailtype`, `context`, `dirty`) VALUES (NULL, NULL, 'sCONFIRMPASSWORDCHANGE', '{config name=mail}', '{config name=shopName}', 'Passwort vergessen - Passwort zurücksetzen', '{include file=\"string:{config name=emailheaderplain}\"}\r\n\r\nHallo,

im Shop {sShopURL} wurde eine Anfrage gestellt, um Ihr Passwort zurück zu setzen.

Bitte bestätigen Sie den unten stehenden Link, um ein neues Passwort zu definieren.

{sUrlReset}

Dieser Link ist nur für die nächsten 2 Stunden gültig. Danach muss das Zurücksetzen des Passwortes erneut beantragt werden.

Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.

{config name=address}\r\n\r\n{include file=\"string:{config name=emailfooterplain}\"}', '', '0', '', '2', '', '0');
EOD;
        $this->addSql($sql);

        $this->addSql("DELETE FROM `s_core_config_mails` WHERE `name` = 'sPASSWORD';");
    }
}
