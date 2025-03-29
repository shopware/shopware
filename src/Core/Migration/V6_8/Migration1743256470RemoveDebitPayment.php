<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1743256470RemoveDebitPayment extends MigrationStep
{
    public const METHOD_HANDLER = 'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\DebitPayment';

    public function getCreationTimestamp(): int
    {
        return 1743256470;
    }

    public function update(Connection $connection): void
    {
        $connection->update(
            'payment_method',
            [
                'handler_identifier' => DefaultPayment::class,
                'active' => 0,
            ],
            ['handler_identifier' => self::METHOD_HANDLER],
        );
    }
}
