<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('storefront')]
class ThemeException extends HttpException
{
    public const THEME_MEDIA_IN_USE_EXCEPTION = 'THEME__MEDIA_IN_USE_EXCEPTION';
    public const THEME_SALES_CHANNEL_NOT_FOUND = 'THEME__SALES_CHANNEL_NOT_FOUND';
    public const INVALID_THEME_BY_NAME = 'THEME__INVALID_THEME';
    public const INVALID_THEME_BY_ID = 'THEME__INVALID_THEME_BY_ID';

    public static function themeMediaStillInUse(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::THEME_MEDIA_IN_USE_EXCEPTION,
            'Media entity is still in use by a theme'
        );
    }

    public static function salesChannelNotFound(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::THEME_SALES_CHANNEL_NOT_FOUND,
            'The sales channel with the id {{ id }} could not be found',
            ['id' => $salesChannelId]
        );
    }

    public static function couldNotFindThemeByName(string $themeName): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new InvalidThemeException($themeName);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_THEME_BY_NAME,
            'Unable to find the theme by name "{{ themeName }}"',
            ['themeName' => $themeName]
        );
    }

    public static function couldNotFindThemeById(string $themeId): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new InvalidThemeException($themeId);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_THEME_BY_ID,
            'Unable to find the theme by ID "{{ themeId }}"',
            ['themeId' => $themeId]
        );
    }
}
