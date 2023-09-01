<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - Will be removed. use \Shopware\Core\Framework\Store\StoreException::extensionThemeStillInUse instead
 */
#[Package('merchant-services')]
class ExtensionThemeStillInUseException extends StoreException
{
    public function __construct(
        string $id,
        array $parameters = [],
        ?\Throwable $e = null
    ) {
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
