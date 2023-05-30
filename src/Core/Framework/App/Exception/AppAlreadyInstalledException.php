<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class AppAlreadyInstalledException extends ShopwareHttpException
{
    public function __construct(
        string $appName,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'App with name "{{appName}}" is already installed.',
            ['appName' => $appName],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__APP_ALREADY_INSTALLED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
