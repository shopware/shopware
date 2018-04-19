<?php

class Migrations_Migration636 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // Do not execute on update, only on a fresh install
        if ($modus === self::MODUS_INSTALL) {
            $this->updateCmsSupportFields();
            $this->updateCmsSupport();
        }
        $this->updateFormLabels();
        $this->updateCoreMenu();
        $this->updateCoreConfigElements();
        $this->updateCoreConfigForms();

        $name = 'sREGISTERCONFIRMATION';
        $contentHtml = '<p>\nHallo {salutation} {firstname} {lastname},<br/><br/>\n\nvielen Dank für Ihre Anmeldung in unserem Shop.<br/><br/>\n\nSie erhalten Zugriff über Ihre E-Mail-Adresse <strong>{sMAIL}</strong><br/>\nund dem von Ihnen gewählten Kennwort.<br/><br/>\n\nSie können sich Ihr Kennwort jederzeit per E-Mail erneut zuschicken lassen.\n</p>';
        $this->updateTemplateHtml($name, $contentHtml);
    }

    /**
     * Helper method to prefix and suffix the mail templates with the configuration values
     *
     * @param string $content
     * @return string
     */
    private function convertTemplateHtml($content)
    {
        $header = '{include file=\"string:{config name=emailheaderhtml}\"}';
        $footer = '{include file=\"string:{config name=emailfooterhtml}\"}';

        return $header . "\r\n<br/><br/>\r\n" . $content . "\r\n<br/><br/>\r\n" . $footer;
    }

    /**
     * Decorates and updates html mail templates in s_core_config_mails
     *
     * @param string $name
     * @param string $contentHtml
     */
    private function updateTemplateHtml($name, $contentHtml)
    {
        $contentHtml = $this->convertTemplateHtml($contentHtml);
        $this->updateEmailTemplateHtml($name, $contentHtml);
    }

    /**
     * Updates the HTML content in s_core_config_mails
     *
     * @param string $name
     * @param string $contentHtml
     */
    private function updateEmailTemplateHtml($name, $contentHtml)
    {
        $sql = <<<SQL
UPDATE `s_core_config_mails` SET `contentHTML` = "$contentHtml" WHERE `name` = "$name" AND dirty = 0;
SQL;
        $this->addSql($sql);
    }

    /**
     * Updates labels in s_core_menu
     */
    private function updateCoreMenu()
    {
        $sql = <<<SQL
UPDATE `s_core_menu` SET `name` = 'E-Mail-Vorlagen'
WHERE `name` = 'eMail-Vorlagen'
AND `controller` = 'Mail'
AND `action`='Index';
SQL;
        $this->addSql($sql);
    }

    /**
     * Updates the labels in table s_core_config_forms
     */
    private function updateFormLabels()
    {
        $sql = <<<SQL
SET @parent = (SELECT `id` FROM `s_core_config_forms` WHERE `name` = 'Checkout' LIMIT 1);
UPDATE `s_core_config_elements` SET `label` = 'Bestell-Abschluss-E-Mail versenden' WHERE `form_id` = @parent AND `name`='sendOrderMail';
SQL;

        $this->addSql($sql);
    }

    /**
     * Updates the labels in s_cms_support_fields
     */
    private function updateCmsSupportFields()
    {
        $dataSet = [];
        $dataSet[] = [
            'new' => 'E-Mail-Adresse',
            'name' => 'email',
            'typ' => 'email',
            'required' => 1,
            'supportID' => 5,
            'label' => 'eMail-Adresse',
            'class' => 'normal',
        ];
        $dataSet[] = [
            'new' => 'E-Mail-Adresse',
            'name' => 'email',
            'typ' => 'text',
            'required' => 1,
            'supportID' => 9,
            'label' => 'eMail-Adresse',
            'class' => 'normal',
        ];
        $dataSet[] = [
            'new' => 'E-Mail-Adresse',
            'name' => 'email',
            'typ' => 'text',
            'required' => 1,
            'supportID' => 10,
            'label' => 'eMail-Adresse',
            'class' => 'normal',
        ];
        $dataSet[] = [
            'new' => 'E-Mail-Adresse',
            'name' => 'email',
            'typ' => 'text',
            'required' => 1,
            'supportID' => 16,
            'label' => 'eMail-Adresse',
            'class' => 'normal',
        ];
        $dataSet[] = [
            'new' => 'E-Mail',
            'name' => 'email',
            'typ' => 'text',
            'required' => 1,
            'supportID' => 8,
            'label' => 'eMail',
            'class' => 'normal',
        ];

        foreach ($dataSet as $data) {
            $sql = <<<SQL
UPDATE `s_cms_support_fields` SET `label` = '$data[new]'
WHERE `name`='$data[name]'
AND `typ`='$data[typ]'
AND `required`=$data[required]
AND `supportID`=$data[supportID]
AND `label`='$data[label]'
AND `class`='$data[class]';
SQL;

            $this->addSql($sql);
        }
    }

    /**
     * Updates text and templates in s_cms_support
     */
    private function updateCmsSupport()
    {
        $text = '<p>Schreiben Sie uns eine E-Mail.</p>\r\n<p>Wir freuen uns auf Ihre Kontaktaufnahme.</p>';
        $emailTemplate = 'Kontaktformular Shopware Demoshop\r\n\r\nAnrede: {sVars.anrede}\r\nVorname: {sVars.vorname}\r\nNachname: {sVars.nachname}\r\nE-Mail: {sVars.email}\r\nTelefon: {sVars.telefon}\r\nBetreff: {sVars.betreff}\r\nKommentar: \r\n{sVars.kommentar}\r\n\r\n\r\n';

        // Since there is no dirty flag, we have to rely on the original content as "safe condition"
        $sql = <<<SQL
UPDATE `s_cms_support` SET `text` = '$text', `email_template` = '$emailTemplate'
WHERE `name` = 'Kontaktformular'
AND `isocode` = 'de'
SQL;
        $this->addSql($sql);
    }

    /**
     * Updates labels and descriptions in s_core_config_elements
     */
    private function updateCoreConfigElements()
    {
        $sql = <<<SQL
UPDATE `s_core_config_elements` SET `label` = 'Warenkorb bei E-Mail-Benachrichtigung ausblenden',
 `description` = 'Warenkorb bei aktivierter E-Mail-Benachrichtigung und nicht vorhandenem Lagerbestand ausblenden'
 WHERE `name` = 'deactivatebasketonnotification'
 AND `label` = 'Warenkorb bei eMail-Benachrichtigung ausblenden';
SQL;

        $this->addSql($sql);

        $sql = <<<SQL
UPDATE `s_core_config_elements` SET `label` = 'Shopbetreiber E-Mail'
WHERE `name` = 'mail'
AND `label` = 'Shopbetreiber eMail';
SQL;

        $this->addSql($sql);
    }

    /**
     * Updates the labels in s_core_config_forms
     */
    private function updateCoreConfigForms()
    {
        $sql = <<<SQL
UPDATE `s_core_config_forms` SET `label` = 'E-Mail-Einstellungen'
WHERE `name` = 'Frontend60'
AND `label` = 'eMail-Einstellungen';
SQL;

        $this->addSql($sql);
    }
}
