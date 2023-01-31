<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1566460168UpdateTexts extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1566460168;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->updateInvoice($connection);
        $this->updateDirectDebit($connection);
        $this->updateCashOnDelivery($connection);
    }

    private function updateInvoice(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first. This is to avoid defaults on payment.\'
            WHERE `description` = \'Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.\'
            AND `name` = \'Invoice\';
        ');

        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'Sie zahlen einfach und bequem auf Rechnung. Shopware bietet z.B. auch die Möglichkeit, Rechnungen automatisiert erst ab der 2. Bestellung für Kunden zur Verfügung zu stellen, um Zahlungsausfälle zu vermeiden.\'
            WHERE `description` = \'Sie zahlen einfach und bequem auf Rechnung. Shopware bietet z.B. auch die Möglichkeit, Rechnung automatisiert erst ab der 2. Bestellung für Kunden zur Verfügung zu stellen, um Zahlungsausfälle zu vermeiden.\'
            AND `name` = \'Rechnung\';
        ');
    }

    private function updateCashOnDelivery(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'Payment upon receipt of goods.\'
            WHERE `description` = \'Pay when you get the order\'
            AND `name` = \'Cash on delivery\';
        ');

        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'Zahlung bei Erhalt der Ware.\'
            WHERE `description` = \'\'
            AND `name` = \'Nachnahme\';
        ');
    }

    private function updateDirectDebit(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'Pre-authorized payment, funds are withdrawn directly from the debited account.\'
            WHERE `description` =\'Additional text\'
            AND `name` = \'Direct Debit\';
        ');

        $connection->executeStatement('
            UPDATE `payment_method_translation`
            SET `description` = \'Vorab autorisierte Zahlungsvereinbarung, Zahlungen werden direkt vom zu belastenden Konto abgebucht.\'
            WHERE `description` = \'Zusatztext\'
            AND `name` = \'Lastschrift\';
        ');
    }
}
