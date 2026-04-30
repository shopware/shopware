<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1776674347RegisterFlowIndexerForPaymentMethodChangedFlow extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776674347;
    }

    public function update(Connection $connection): void
    {
        // Re-register the flow indexer so the flow inserted by
        // Migration1770705203AddPaymentMethodChangedFlowAndMailTemplate
        // gets picked up by the flow template/index on existing installs.
        $this->registerIndexer($connection, 'flow.indexer');
    }
}
