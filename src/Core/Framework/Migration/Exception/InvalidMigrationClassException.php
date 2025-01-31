<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.7.0 - reason:remove-exception - Will be removed. Use \Shopware\Core\Framework\Migration\MigrationException::invalidMigrationClass instead
 */
#[Package('framework')]
class InvalidMigrationClassException extends ShopwareHttpException
{
    public function __construct(
        string $class,
        string $path
    ) {
        parent::__construct(
            'Unable to load migration {{ class }} at path {{ path }}',
            ['class' => $class, 'path' => $path]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_MIGRATION';
    }
}
