<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class OpenNewTabResponseFactory implements ActionButtonResponseFactoryInterface
{
    public function __construct(private readonly QuerySigner $signer)
    {
    }

    public function supports(string $actionType): bool
    {
        return $actionType === OpenNewTabResponse::ACTION_TYPE;
    }

    public function create(AppAction $action, array $payload, Context $context): ActionButtonResponse
    {
        $this->validate($payload, $action->getActionId());

        $appSecret = $action->getAppSecret();
        if ($appSecret) {
            $payload['redirectUrl'] = (string) $this->signer->signUri($payload['redirectUrl'], $appSecret, $context);
        }

        $response = new OpenNewTabResponse();
        $response->assign($payload);

        return $response;
    }

    private function validate(array $payload, string $actionId): void
    {
        if (!isset($payload['redirectUrl']) || empty($payload['redirectUrl'])) {
            throw new ActionProcessException($actionId, 'The app provided an invalid redirectUrl');
        }
    }
}
