<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1557753352AddPaymentHandler extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1557753352;
    }

    public function update(Connection $connection): void
    {
        $mapping = [
            'Invoice' => InvoicePayment::class,
            'Paid in advance' => PrePayment::class,
            'Cash on delivery' => CashPayment::class,
            'Direct Debit' => DebitPayment::class,
        ];

        $paymentMethods = $connection->fetchAll('
            SELECT id, `handler_identifier`, `name` FROM `payment_method` 
                LEFT JOIN `payment_method_translation` ON `payment_method`.`id` = `payment_method_translation`.`payment_method_id`
                WHERE `payment_method_translation`.`language_id` = :languageId',
            [
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );

        foreach ($paymentMethods as $paymentMethod) {
            if (!array_key_exists($paymentMethod['name'], $mapping)) {
                continue;
            }

            $connection->update(
                'payment_method',
                ['handler_identifier' => $mapping[$paymentMethod['name']]],
                ['id' => $paymentMethod['id']]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
