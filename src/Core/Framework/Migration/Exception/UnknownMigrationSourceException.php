<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class UnknownMigrationSourceException extends ShopwareHttpException
{
    public function __construct(private readonly string $name)
    {
        parent::__construct(
            'No source registered for "{{ name }}"',
            ['name' => $name]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_MIGRATION_SOURCE';
    }

    public function getParameters(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
