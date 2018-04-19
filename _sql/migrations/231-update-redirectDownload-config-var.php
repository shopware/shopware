<?php
class Migrations_Migration231 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            ALTER TABLE  `s_core_config_elements` CHANGE  `description`  `description` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL ;

            SET @oldElementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'redirectDownload' LIMIT 1);
            UPDATE s_core_config_elements SET form_id = -1 WHERE id = @oldElementId;

            SET @formId = (SELECT id FROM `s_core_config_forms` WHERE `name` LIKE 'Esd');

            INSERT IGNORE INTO `s_core_config_elements`
            (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
            VALUES (@formId, 'esdDownloadStrategy', 'i:1;',
            'Downloadoption für ESD Dateien',
            '<b>Achtung</b>: Diese Einstellung könnte die Funktionalität der ESD Downloads beeinträchtigen. Ändern Sie hier nur die Einstellung falls Sie wissen, was Sie tun.<br><br>Downloadstrategie für ESD Dateien.<br><b>Link</b>: Unter umständen Unsicher, da der Link von Außen eingesehen werden kann.<br><b>PHP</b>: Der Link kann nicht eingesehen werden. PHP liefert die Datei aus. Dies kann zu Problemen bei größeren Dateien führen.<br><b>X-Sendfile</b>: Unterstütz größere Dateien und ist sicher. Benötigt das X-Sendfile Apache Module. <br><b>X-Accel</b>: Äquivalent zum X-Sendfile. Benötigt das Nginx Modul X-Accel.',
            'select', '1', '4', '0', NULL, NULL,
            'a:1:{s:5:"store";a:4:{i:0;a:2:{i:0;i:0;i:1;s:4:"Link";}i:1;a:2:{i:0;i:1;i:1;s:3:"PHP";}i:2;a:2:{i:0;i:2;i:1;s:20:"X-Sendfile (Apache2)";}i:3;a:2:{i:0;i:3;i:1;s:15:"X-Accel (Nginx)";}}}'
            );

            SET @newElementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'esdDownloadStrategy' LIMIT 1);
            INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
            VALUES (@newElementId, '2',
            'Download strategy for ESD files',
            '<b>Warning</b>: Changing this setting might break ESD downloads. If not sure, use default (PHP)<br><br>Strategy to generate the download links for ESD files. <br><b>Link</b>: Better performance, but possibly insecure <br><b>PHP</b>: More secure, but memory consuming, specially for bigger files <br><b>X-Sendfile</b>: Secure and lightweight, but requires X-Sendfile module and Apache2 web server <br><b>X-Accel</b>: Equivalent to X-Sendfile, but requires Nginx web server instead'
            );

            INSERT IGNORE INTO s_core_config_values (element_id, shop_id, value)
            SELECT @newElementId as element_id, shop_id, IF(STRCMP(value, 'b:0;') = 0,'i:1;','i:0;') as value
            FROM s_core_config_values
            WHERE element_id = @oldElementId;
EOD;
        $this->addSql($sql);
    }
}
