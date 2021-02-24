<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class ExtensionRequiresNewPrivilegesException extends ShopwareHttpException
{
    public static function fromPrivilegeList(string $appName, array $privileges): self
    {
        return new self(
            'Updating "{{app}}" requires new privileges "{{privileges}}".',
            [
                'app' => $appName,
                'privileges' => implode(';', $privileges),
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__EXTENSION_REQUIRES_NEW_PRIVILEGES';
    }
}
