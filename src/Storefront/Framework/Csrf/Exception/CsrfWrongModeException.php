<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CsrfWrongModeException extends ShopwareHttpException
{
    public function __construct(string $requiredMode)
    {
        parent::__construct(
            'CSRF has the wrong mode. Please make sure the mode is set to "{{mode}}"',
            ['mode' => $requiredMode]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__CSRF_WRONG_MODE';
    }
}
