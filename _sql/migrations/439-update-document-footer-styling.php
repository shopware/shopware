<?php
class Migrations_Migration439 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus !== \Shopware\Framework\Migration\AbstractMigration::MODUS_INSTALL) {
            return;
        }

        $sql = <<<'EOD'
UPDATE `s_core_documents_box`

SET `value` = '<table style="vertical-align: top;" width="100%" border="0">\r\n<tbody>\r\n<tr valign="top">\r\n<td style="width: 25%;">\r\n<p><span style="font-size: xx-small;">Demo GmbH</span></p>\r\n<p><span style="font-size: xx-small;">Steuer-Nr <br />UST-ID: <br />Finanzamt </span><span style="font-size: xx-small;">Musterstadt</span></p>\r\n</td>\r\n<td style="width: 25%;">\r\n<p><span style="font-size: xx-small;">Bankverbindung</span></p>\r\n<p><span style="font-size: xx-small;">Sparkasse Musterstadt<br />BLZ: <br />Konto: </span></p>\r\n<span style="font-size: xx-small;">aaaa<br /></span></td>\r\n<td style="width: 25%;">\r\n<p><span style="font-size: xx-small;">AGB<br /></span></p>\r\n<p><span style="font-size: xx-small;">Gerichtsstand ist Musterstadt<br />Erf&uuml;llungsort Musterstadt<br />Gelieferte Ware bleibt bis zur vollst&auml;ndigen Bezahlung unser Eigentum</span></p>\r\n</td>\r\n<td style="width: 25%;">\r\n<p><span style="font-size: xx-small;">Gesch&auml;ftsf&uuml;hrer</span></p>\r\n<p><span style="font-size: xx-small;">Max Mustermann</span></p>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>'

WHERE `value` LIKE '<table style="height: 90px;" border="0" width="100%">\r\n<tbody>\r\n<tr valign="top">\r\n<td style="width: 25%;">\r\n<p><span style="font-size: xx-small;">Demo GmbH</span></p>\r\n<p><span style="font-size: xx-small;">Steuer-Nr <br />UST-ID: <br />Finanzamt </span><span style="font-size: xx-small;">Musterstadt</span></p>\r\n</td>\r\n<td style="width: 25%;">\r\n<p><span style="font-size: xx-small;">Bankverbindung</span></p>\r\n<p><span style="font-size: xx-small;">Sparkasse Musterstadt<br />BLZ: <br />Konto: </span></p>\r\n<span style="font-size: xx-small;">aaaa<br /></span></td>\r\n<td style="width: 25%;">\r\n<p><span style="font-size: xx-small;">AGB<br /></span></p>\r\n<p><span style="font-size: xx-small;">Gerichtsstand ist Musterstadt<br />Erf&uuml;llungsort Musterstadt<br />Gelieferte Ware bleibt bis zur vollst&auml;ndigen Bezahlung unser Eigentum</span></p>\r\n</td>\r\n<td style="width: 25%;">\r\n<p><span style="font-size: xx-small;">Gesch&auml;ftsf&uuml;hrer</span></p>\r\n<p><span style="font-size: xx-small;">Max Mustermann</span></p>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>'
EOD;
        $this->addSql($sql);
    }
}
