<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.7.0 - reason:remove-exception - Will be removed. Use \Shopware\Core\Framework\Migration\MigrationException::migrationError instead
 */
#[Package('framework')]
class MigrateException extends ShopwareHttpException
{
    public function __construct(
        string $message,
        ?\Throwable $previous
    ) {
        parent::__construct('Migration error: {{ errorMessage }}', ['errorMessage' => $message], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__MIGRATION_ERROR';
    }
}
