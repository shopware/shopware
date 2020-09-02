<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

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
