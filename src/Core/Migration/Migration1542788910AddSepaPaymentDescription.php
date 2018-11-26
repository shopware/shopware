<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1542788910AddSepaPaymentDescription extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542788910;
    }

    public function update(Connection $connection): void
    {
        $connection->update('payment_method_translation', ['additional_description' => 'SEPA invoice'], ['payment_method_id' => Uuid::fromHexToBytes(Defaults::PAYMENT_METHOD_SEPA)]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
