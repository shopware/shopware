<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - Will be removed. use \Shopware\Core\Framework\Store\StoreException::extensionThemeStillInUse instead
 */
#[Package('services-settings')]
class ExtensionThemeStillInUseException extends StoreException
{
    public function __construct(
        string $id,
        array $parameters = [],
        ?\Throwable $e = null
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use StoreException::extensionThemeStillInUse instead.')
        );

        $parameters['id'] = $id;

        parent::__construct(
            Response::HTTP_FORBIDDEN,
            StoreException::EXTENSION_THEME_STILL_IN_USE,
            'The extension with id "{{id}}" can not be removed because its theme is still assigned to a sales channel.',
            $parameters,
            $e
        );
    }
}
