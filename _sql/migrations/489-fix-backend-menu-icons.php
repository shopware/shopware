<?php
class Migrations_Migration489 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->updateProductMenu();
    }

    public function updateProductMenu()
    {
        /** Article */
        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'ico package_green article--main'
        WHERE `name` = 'Artikel'
        AND `controller` = 'Article';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-inbox--plus article--add-article'
        WHERE `name` = 'Anlegen'
        AND `controller` = 'Article'
        AND `action` = 'Detail';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-ui-scroll-pane-list article--overview'
        WHERE `name` = 'Übersicht'
        AND `controller` = 'ArticleList'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-blue-folders-stack article--categories'
        WHERE `name` = 'Kategorien'
        AND `controller` = 'Category'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-property-blue article--properties'
        WHERE `name` = 'Eigenschaften'
        AND `controller` = 'Property'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-truck article--manufacturers'
        WHERE `name` = 'Hersteller'
        AND `controller` = 'Supplier'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-balloon article--ratings'
        WHERE `name` = 'Bewertungen'
        AND `controller` = 'Vote'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        /** Contents */
        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'ico2 note03 contents--main'
        WHERE `name` = 'Inhalte'
        AND `controller` = 'Content';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-documents contents--shopsites'
        WHERE `name` = 'Shopseiten'
        AND `controller` = 'Site'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-application-blog contents--blog'
        WHERE `name` = 'Blog'
        AND `controller` = 'Blog'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-application-form contents--forms'
        WHERE `name` = 'Formulare'
        AND `controller` = 'Form'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-arrow-circle-double-135 contents--import-export'
        WHERE `name` = 'Import/Export'
        AND `controller` = 'ImportExport'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-inbox-image contents--media-manager'
        WHERE `name` = 'Medienverwaltung'
        AND `controller` = 'MediaManager'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        /** Customers */
        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'ico customer customers--main'
        WHERE `name` = 'Kunden'
        AND `controller` = 'Customer';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-user--plus customers--add-customer'
        WHERE `name` = 'Anlegen'
        AND `controller` = 'Customer'
        AND `action` = 'Detail';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-ui-scroll-pane-detail customers--customer-list'
        WHERE `name` = 'Kundenliste'
        AND `controller` = 'Customer'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-sticky-notes-pin customers--orders'
        WHERE `name` = 'Bestellungen'
        AND `controller` = 'Order'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        /** Properties */
        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'ico2 wrench_screwdriver settings--main'
        WHERE `name` = 'Einstellungen'
        AND `controller` = 'ConfigurationMenu';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-bin-full settings--performance'
        WHERE `name` = 'Performance'
        AND `controller` = 'Performance'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-edit-shade settings--performance--cache'
        WHERE `name` = 'Shopcache leeren'
        AND `controller` = 'Performance'
        AND `action` =  'Config';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-wrench-screwdriver settings--basic-settings'
        WHERE `name` = 'Grundeinstellungen'
        AND `controller` = 'Config'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-blueprint settings--system-info'
        WHERE `name` = 'Systeminfo'
        AND `controller` = 'Systeminfo'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-user-silhouette settings--user-management'
        WHERE `name` = 'Benutzerverwaltung'
        AND `controller` = 'UserManager'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-cards-stack settings--logfile'
        WHERE `name` = 'Logfile'
        AND `controller` = 'Log'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-envelope--arrow settings--delivery-charges'
        WHERE `name` = 'Versandkosten'
        AND `controller` = 'Shipping'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-credit-cards settings--payment-methods'
        WHERE `name` = 'Zahlungsarten'
        AND `controller` = 'Payment'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-mail--pencil settings--mail-presets'
        WHERE `name` = 'eMail-Vorlagen'
        AND `controller` = 'Mail'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-funnel--exclamation settings--riskmanagement'
        WHERE `name` = 'Riskmanagment'
        AND `controller` = 'RiskManagment'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-edit-shade settings--snippets'
        WHERE `name` = 'Textbausteine'
        AND `controller` = 'Snippet'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-application-icon-large settings--theme-manager'
        WHERE `name` = 'Theme Manager'
        AND `controller` = 'Theme'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-application-block settings--plugin-manager'
        WHERE `name` = 'Plugin Manager'
        AND `controller` = 'PluginManager'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        /** Marketing */
        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'ico2 chart_bar01 marketing--main'
        WHERE `name` = 'Marketing'
        AND `controller` = 'Marketing';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-chart marketing--analyses'
        WHERE `name` = 'Auswertungen'
        AND `controller` = 'AnalysisMenu';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-report-paper marketing--analyses--overview'
        WHERE `name` = 'Übersicht'
        AND `controller` = 'Overview'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-chart marketing--analyses--stats-charts'
        WHERE `name` = 'Statistiken / Diagramme'
        AND `controller` = 'Analystics'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-chart-down-color marketing--analyses--abort-analyses'
        WHERE `name` = 'Abbruch-Analyse'
        AND `controller` = 'CanceledOrder'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-mail-forward marketing--analyses--email-notification'
        WHERE `name` = 'E-Mail Benachrichtigungen'
        AND `controller` = 'Notification'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-image-medium marketing--banner'
        WHERE `name` = 'Banner'
        AND `controller` = 'Banner'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-pin marketing--shopping-worlds'
        WHERE `name` = 'Einkaufswelten'
        AND `controller` = 'Emotion'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-star marketing--premium-items'
        WHERE `name` = 'Pr&auml;mienartikel'
        AND `controller` = 'Premium'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-mail-open-image marketing--vouchers'
        WHERE `name` = 'Gutscheine'
        AND `controller` = 'Voucher'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-folder-export marketing--product-exports'
        WHERE `name` = 'Produktexporte'
        AND `controller` = 'ProductFeed'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-xfn-colleague marketing--partner-program'
        WHERE `name` = 'Partnerprogramm'
        AND `controller` = 'Partner'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-paper-plane marketing--newsletters'
        WHERE `name` = 'Newsletter'
        AND `controller` = 'NewsletterManager'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        /** Misc */
        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-lifebuoy misc--help'
        WHERE `name` = 'Hilfe'
        AND `controller` = 'HelpMenu';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-balloons-box misc--help--board'
        WHERE `name` = 'Zum Forum'
        AND `controller` = 'Forum';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-lifebuoy misc--help--online-help'
        WHERE `name` = 'Onlinehilfe aufrufen'
        AND `controller` = 'Onlinehelp';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-briefcase--arrow misc--send-feedback'
        WHERE `name` = 'Feedback senden'
        AND `controller` = 'Beta Feedback'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-arrow-continue-090 misc--software-update'
        WHERE `name` = 'SwagUpdate'
        AND `controller` = 'SwagUpdate'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-keyboard-command misc--shortcuts'
        WHERE `name` = 'Tastaturk&uuml;rzel'
        AND `controller` = 'ShortCutMenu'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-shopware-logo misc--about-shopware'
        WHERE `name` = 'Über Shopware'
        AND `controller` = 'AboutShopware'
        AND `action` = 'Index';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        UPDATE `s_core_menu` SET `class` = 'sprite-credit-cards settings--payment-methods'
        WHERE `name` = 'Zahlungen'
        AND `controller` = 'Payments';
EOD;
        $this->addSql($sql);

    }
}
