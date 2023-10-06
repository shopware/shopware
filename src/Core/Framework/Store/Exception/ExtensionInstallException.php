<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed. Use StoreException::extensionInstallException instead.
 */
#[Package('services-settings')]
class ExtensionInstallException extends StoreException
{
    public function __construct(string $message, array $parameters = [], ?\Throwable $e = null)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'Use StoreException::extensionInstallException instead.')
        );

        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            StoreException::EXTENSION_INSTALL,
            $message,
            $parameters,
            $e
        );
    }
}
