<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class MigrateException extends ShopwareHttpException
{
    public function __construct(string $message)
    {
        parent::__construct('Migration error: {{ errorMessage }}', ['errorMessage' => $message]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__MIGRATION_ERROR';
    }
}
