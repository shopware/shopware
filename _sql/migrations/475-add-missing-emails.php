<?php

class Migrations_Migration475 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus !== self::MODUS_INSTALL) {
            return;
        }
        $this->updateEmail();
        $this->insertTranslation();
    }

    private function updateEmail()
    {
        $name = 'sORDERSTATEMAIL7';
        $content = 'Hallo {if $sUser.billing_salutation eq \"mr\"}Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n\nder Bestellstatus für Ihre Bestellung {$sOrder.ordernumber} hat sich geändert!\nDie Bestellung hat jetzt den Status: {$sOrder.status_description}.\n\nDen aktuellen Status Ihrer Bestellung  können Sie  auch jederzeit auf unserer Webseite im  Bereich \"Mein Konto\" - \"Meine Bestellungen\" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';

        $content = $this->convertTemplatePlain($content);

        $sql = <<<SQL
UPDATE `s_core_config_mails` SET `content` = "$content" WHERE `name` = "$name" AND dirty = 0
SQL;
        $this->addSql($sql);
    }


    private function insertTranslation()
    {
        $content = "Hello {if \$sUser.billing_salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.billing_firstname} {\$sUser.billing_lastname},\n\nThe order status of your order {\$sOrder.ordernumber} has changed!\nYour order now has the following status: {\$sOrder.status_description}.\n\nYou can check the current status of your order on our website under \"My account\" - \"My orders\" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.\n\nBest regards,\nYour team of {config name=shopName}";

        $content = stripslashes($this->convertTemplatePlain($content));

        $data = [
            'content' => $content,
            'subject' => 'Your order with {config name=shopName}'
        ];

        $data = serialize($data);

        $sql = <<<SQL
INSERT INTO `s_core_translations`
SET `objectdata`= '$data',
`objectkey` = (SELECT id FROM  `s_core_config_mails` WHERE `name` = "sORDERSTATEMAIL7" LIMIT 1),
objecttype = 'config_mails', dirty = 0, objectlanguage = 2;
SQL;

        $this->addSql($sql);
    }

    /**
     * Helper method to prefix and suffix the mail templates with the configuration values
     *
     * @param string $content
     * @return string
     */
    private function convertTemplatePlain($content)
    {
        $header = '{include file=\"string:{config name=emailheaderplain}\"}';
        $footer = '{include file=\"string:{config name=emailfooterplain}\"}';

        return $header."\r\n\r\n".$content."\r\n\r\n".$footer;
    }
}
