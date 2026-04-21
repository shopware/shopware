<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1776809984RegisterPaymentMethodIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776809984;
    }

    public function update(Connection $connection): void
    {
        // Catch-up re-index for shops that ran
        // Migration1743256470RemoveDebitPayment, which deletes rows from
        // payment_method and can invalidate PaymentDistinguishableNameGenerator
        // output without scheduling a payment_method.indexer refresh.
        $this->registerIndexer($connection, 'payment_method.indexer');
    }
}
