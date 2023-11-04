<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('storefront')]
class ThemeException extends HttpException
{
    public const THEME_MEDIA_IN_USE_EXCEPTION = 'THEME__MEDIA_IN_USE_EXCEPTION';

    public static function themeMediaStillInUse(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::THEME_MEDIA_IN_USE_EXCEPTION,
            'Media entity is still in use by a theme'
        );
    }
}
