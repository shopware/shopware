<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class OpenModalResponseFactory implements ActionButtonResponseFactoryInterface
{
    private const VALID_MODAL_SIZES = [
        'small',
        'medium',
        'large',
        'fullscreen',
    ];

    public function __construct(private readonly QuerySigner $signer)
    {
    }

    public function supports(string $actionType): bool
    {
        return $actionType === OpenModalResponse::ACTION_TYPE;
    }

    public function create(AppAction $action, array $payload, Context $context): ActionButtonResponse
    {
        $this->validate($payload, $action->getActionId());

        $appSecret = $action->getAppSecret();
        if ($appSecret) {
            $payload['iframeUrl'] = (string) $this->signer->signUri($payload['iframeUrl'], $appSecret, $context);
        }

        $response = new OpenModalResponse();
        $response->assign($payload);

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validate(array $payload, string $actionId): void
    {
        if (empty($payload['iframeUrl'])) {
            throw AppException::actionButtonProcessException($actionId, 'The app provided an invalid iframeUrl');
        }

        if (!isset($payload['size']) || !\in_array($payload['size'], self::VALID_MODAL_SIZES, true)) {
            throw AppException::actionButtonProcessException($actionId, 'The app provided an invalid size');
        }
    }
}
