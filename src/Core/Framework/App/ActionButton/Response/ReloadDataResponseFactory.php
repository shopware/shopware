<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ReloadDataResponseFactory implements ActionButtonResponseFactoryInterface
{
    public function supports(string $actionType): bool
    {
        return $actionType === ReloadDataResponse::ACTION_TYPE;
    }

    public function create(AppAction $action, array $payload, Context $context): ActionButtonResponse
    {
        $response = new ReloadDataResponse();
        $response->assign($payload);

        return $response;
    }
}
