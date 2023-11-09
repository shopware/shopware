<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed. Use ThemeException::invalidTheme instead.
 */
#[Package('storefront')]
class InvalidThemeException extends ThemeException
{
    public function __construct(string $themeName)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'Use ThemeException::invalidTheme instead.')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            ThemeException::INVALID_THEME_BY_NAME,
            'Unable to find the theme "{{ themeName }}"',
            ['themeName' => $themeName]
        );
    }
}
