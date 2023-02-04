<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class MigrateException extends ShopwareHttpException
{
    public function __construct(
        string $message,
        \Exception $previous
    ) {
        parent::__construct('Migration error: {{ errorMessage }}', ['errorMessage' => $message], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__MIGRATION_ERROR';
    }
}
