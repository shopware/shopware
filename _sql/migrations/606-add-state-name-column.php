<?php
class Migrations_Migration606 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_core_states` ADD `name` VARCHAR(55) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `id`;");

        $this->addSql("UPDATE s_core_states SET `name` = 'cancelled' WHERE `description` LIKE 'Abgebrochen' AND `group` LIKE 'state';");
        $this->addSql("UPDATE s_core_states SET `name` = 'open' WHERE `description` LIKE 'Offen' AND `group` LIKE 'state';");
        $this->addSql("UPDATE s_core_states SET `name` = 'in_process' WHERE `description` LIKE 'In Bearbeitung (Wartet)' AND `group` LIKE 'state';");
        $this->addSql("UPDATE s_core_states SET `name` = 'completed' WHERE `description` LIKE 'Komplett abgeschlossen' AND `group` LIKE 'state';");
        $this->addSql("UPDATE s_core_states SET `name` = 'partially_completed' WHERE `description` LIKE 'Teilweise abgeschlossen' AND `group` LIKE 'state';");
        $this->addSql("UPDATE s_core_states SET `name` = 'cancelled_rejected' WHERE `description` LIKE 'Storniert / Abgelehnt' AND `group` LIKE 'state';");
        $this->addSql("UPDATE s_core_states SET `name` = 'ready_for_delivery' WHERE `description` LIKE 'Zur Lieferung bereit' AND `group` LIKE 'state';");
        $this->addSql("UPDATE s_core_states SET `name` = 'partially_delivered' WHERE `description` LIKE 'Teilweise ausgeliefert' AND `group` LIKE 'state';");
        $this->addSql("UPDATE s_core_states SET `name` = 'completely_delivered' WHERE `description` LIKE 'Komplett ausgeliefert' AND `group` LIKE 'state';");
        $this->addSql("UPDATE s_core_states SET `name` = 'clarification_required' WHERE `description` LIKE 'Klärung notwendig' AND `group` LIKE 'state';");
        $this->addSql("UPDATE s_core_states SET `name` = 'partially_invoiced' WHERE `description` LIKE 'Teilweise in Rechnung gestellt' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'completely_invoiced' WHERE `description` LIKE 'Komplett in Rechnung gestellt' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'partially_paid' WHERE `description` LIKE 'Teilweise bezahlt' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'completely_paid' WHERE `description` LIKE 'Komplett bezahlt' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = '1st_reminder' WHERE `description` LIKE '1. Mahnung' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = '2nd_reminder' WHERE `description` LIKE '2. Mahnung' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = '3rd_reminder' WHERE `description` LIKE '3. Mahnung' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'encashment' WHERE `description` LIKE 'Inkasso' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'open' WHERE `description` LIKE 'Offen' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'reserved' WHERE `description` LIKE 'Reserviert' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'delayed' WHERE `description` LIKE 'Verzoegert' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 're_crediting' WHERE `description` LIKE 'Wiedergutschrift' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'review_necessary' WHERE `description` LIKE 'Überprüfung notwendig' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'no_credit_approved' WHERE `description` LIKE 'Es wurde kein Kredit genehmigt.' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'the_credit_has_been_preliminarily_accepted' WHERE `description` LIKE 'Der Kredit wurde vorlaeufig akzeptiert.' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'the_credit_has_been_accepted' WHERE `description` LIKE 'Der Kredit wurde genehmigt.' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'the_payment_has_been_ordered_by_hanseatic_bank' WHERE `description` LIKE 'Die Zahlung wurde von der Hanseatic Bank angewiesen.' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'a_time_extension_has_been_registered' WHERE `description` LIKE 'Es wurde eine Zeitverlaengerung eingetragen.' AND `group` LIKE 'payment';");
        $this->addSql("UPDATE s_core_states SET `name` = 'the_process_has_been_cancelled' WHERE `description` LIKE 'Vorgang wurde abgebrochen.' AND `group` LIKE 'payment';");
    }
}
