<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - will be removed with v6.8.0.0
 */
#[Package('framework')]
class AuthThrottledException extends ShopwareHttpException
{
    public function __construct(
        private readonly int $waitTime,
        ?\Throwable $e = null
    ) {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'));

        parent::__construct(
            'Auth throttled for {{ seconds }} seconds.',
            ['seconds' => $this->getWaitTime()],
            $e
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'));

        return 'FRAMEWORK__AUTH_THROTTLED';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'));

        return Response::HTTP_TOO_MANY_REQUESTS;
    }

    public function getWaitTime(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'));

        return $this->waitTime;
    }
}
