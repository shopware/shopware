<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SEPAPayment;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1553679520RefactorPaymentMethods extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553679520;
    }

    public function update(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `payment_method`
            ADD COLUMN `handler_identifier` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT "Shopware\\\Core\\\Checkout\\\Payment\\\Cart\\\PaymentHandler\\\DefaultPayment";'
        );

        // TODO: When merging migrations --> Add to Migration1536233420BasicData
        $connection->update('payment_method', ['handler_identifier' => DebitPayment::class], ['technical_name' => 'debit']);
        $connection->update('payment_method', ['handler_identifier' => InvoicePayment::class], ['technical_name' => 'invoice']);
        $connection->update('payment_method', ['handler_identifier' => SEPAPayment::class], ['technical_name' => 'sepa']);

        /** @var string $ruleId */
        $ruleId = $connection->executeQuery("SELECT `id` FROM `rule` WHERE name = 'Cart >= 0 (Payment)'")->fetchColumn();

        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $cash = Uuid::randomBytes();
        $connection->insert(
            'payment_method',
            [
                'id' => $cash,
                'technical_name' => 'cash',
                'handler_identifier' => CashPayment::class,
                'position' => 1,
                'active' => 1,
                'availability_rule_ids' => json_encode([Uuid::fromBytesToHex($ruleId)]),
                'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            ]
        );
        $connection->insert(
            'payment_method_rule',
            [
                'payment_method_id' => $cash,
                'rule_id' => $ruleId,
                'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            ]
        );
        $connection->insert(
            'payment_method_translation',
            [
                'payment_method_id' => $cash,
                'language_id' => $languageEN,
                'name' => 'Cash on delivery',
                'description' => '<p>Pay when you get the order</p>',
                'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            ]
        );

        $pre = Uuid::randomBytes();
        $connection->insert(
            'payment_method',
            [
                'id' => $pre,
                'technical_name' => 'prepayment',
                'handler_identifier' => PrePayment::class,
                'position' => 2,
                'active' => 1,
                'availability_rule_ids' => json_encode([Uuid::fromBytesToHex($ruleId)]),
                'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            ]
        );
        $connection->insert(
            'payment_method_rule',
            [
                'payment_method_id' => $pre,
                'rule_id' => $ruleId,
                'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            ]
        );
        $connection->insert(
            'payment_method_translation',
            [
                'payment_method_id' => $pre,
                'language_id' => $languageEN,
                'name' => 'Paid in advance',
                'description' => '<p>Pay in advance and get your order afterwards</p>',
                'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `payment_method`
            DROP COLUMN `class`,
            DROP COLUMN `technical_name`,
            DROP COLUMN `percentage_surcharge`,
            DROP COLUMN `absolute_surcharge`,
            DROP COLUMN `template`,
            DROP INDEX `uniq.name`;'
        );

        $connection->exec(
            'ALTER TABLE `payment_method_translation`
            DROP COLUMN `surcharge_text`;'
        );
    }
}
