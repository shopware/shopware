<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class ActionNotFoundException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('The requested app action does not exist');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__APP_ACTION_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
