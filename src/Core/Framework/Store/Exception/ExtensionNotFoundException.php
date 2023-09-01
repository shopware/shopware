<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed.
 */
#[Package('merchant-services')]
class ExtensionNotFoundException extends StoreException
{
    public function __construct(string $message, array $parameters = [], ?\Throwable $e = null)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            StoreException::EXTENSION_NOT_FOUND,
            $message,
            $parameters,
            $e
        );
    }

    /**
     * @deprecated tag:v6.6.0 - will be removed. Use StoreException::extensionNotFoundFromTechnicalName
     */
    public static function fromTechnicalName(string $technicalName): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use StoreException::extensionNotFoundFromTechnicalName instead.')
        );

        return new self(
            'Could not find extension with technical name "{{technicalName}}".',
            ['technicalName' => $technicalName]
        );
    }

    /**
     * @deprecated tag:v6.6.0 - will be removed. Use StoreException::extensionNotFoundFromId
     */
    public static function fromId(string $id): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use StoreException::extensionNotFoundFromId instead.')
        );

        return new self(
            'Could not find extension with id "{{id}}".',
            ['id' => $id]
        );
    }
}
