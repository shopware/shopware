<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - Use \Shopware\Core\Framework\App\AppException::actionButtonProcessException instead
 */
#[Package('core')]
class ActionProcessException extends ShopwareHttpException
{
    public function __construct(
        private readonly string $actionId,
        string $errorMessage,
        ?\Throwable $e = null
    ) {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedClassMessage(self::class, 'v6.6.0.0'));

        parent::__construct(
            'The synchronous action process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $errorMessage],
            $e
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedClassMessage(self::class, 'v6.6.0.0'));

        return 'FRAMEWORK__SYNC_ACTION_PROCESS_INTERRUPTED';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedClassMessage(self::class, 'v6.6.0.0'));

        return Response::HTTP_BAD_REQUEST;
    }

    public function getActionId(): string
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedClassMessage(self::class, 'v6.6.0.0'));

        return $this->actionId;
    }
}
