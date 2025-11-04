<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ThemeException extends HttpException
{
    public const THEME_MEDIA_IN_USE_EXCEPTION = 'THEME__MEDIA_IN_USE_EXCEPTION';
    public const THEME_SALES_CHANNEL_NOT_FOUND = 'THEME__SALES_CHANNEL_NOT_FOUND';
    public const INVALID_THEME_BY_NAME = 'THEME__INVALID_THEME';
    public const INVALID_THEME_BY_ID = 'THEME__INVALID_THEME_BY_ID';
    public const INVALID_SCSS_VAR = 'THEME__INVALID_SCSS_VAR';
    /**
     * @deprecated tag:v6.8.0 - Will be removed, const is no longer used
     */
    public const THEME__COMPILING_ERROR = 'THEME__COMPILING_ERROR';
    public const ERROR_LOADING_RUNTIME_CONFIG = 'THEME__ERROR_LOADING_RUNTIME_CONFIG';
    public const ERROR_LOADING_FROM_PLUGIN_REGISTRY = 'THEME__ERROR_LOADING_THEME_FROM_PLUGIN_REGISTRY';
    public const THEME_ASSIGNMENT = 'THEME__THEME_ASSIGNMENT';
    public const THEME_CREATION_FAILURE = 'THEME__THEME_CREATION_FAILURE';

    /**
     * @deprecated tag:v6.8.0 - will be removed, as the exception is no longer needed, use RestrictDeleteViolationException instead
     */
    public static function themeMediaStillInUse(): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(
                __CLASS__,
                __METHOD__,
                'v6.8.0.0',
                RestrictDeleteViolationException::class
            )
        );

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
            self::$couldNotFindMessage,
            ['entity' => 'sales channel', 'field' => 'id', 'value' => $salesChannelId]
        );
    }

    public static function couldNotFindThemeByName(string $themeName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_THEME_BY_NAME,
            self::$couldNotFindMessage,
            ['entity' => 'theme', 'field' => 'name', 'value' => $themeName]
        );
    }

    public static function couldNotFindThemeById(string $themeId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_THEME_BY_ID,
            self::$couldNotFindMessage,
            ['entity' => 'theme', 'field' => 'id', 'value' => $themeId]
        );
    }

    public static function InvalidScssValue(mixed $value, string $type, string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SCSS_VAR,
            'SCSS Value "{{ value }}" is not valid for type "{{ type }}".',
            ['name' => $name, 'value' => $value, 'type' => $type]
        );
    }

    public static function themeCompileException(string $themeName, string $message = '', ?\Throwable $e = null): ThemeCompileException
    {
        return new ThemeCompileException(
            $themeName,
            $message,
            $e,
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will only return `self` in the future
     *
     * @param array<string, array<int, string>> $themeSalesChannel
     * @param array<string, array<int, string>> $childThemeSalesChannel
     * @param array<string, string> $assignedSalesChannels
     */
    public static function themeAssignmentException(
        string $themeName,
        array $themeSalesChannel,
        array $childThemeSalesChannel,
        array $assignedSalesChannels,
        ?\Throwable $e = null,
    ): self|ThemeAssignmentException {
        if (!Feature::isActive('v6.8.0.0')) {
            return new ThemeAssignmentException(
                $themeName,
                $themeSalesChannel,
                $childThemeSalesChannel,
                $assignedSalesChannels,
                $e,
            );
        }

        $parameters = ['themeName' => $themeName];
        $message = 'Unable to deactivate or uninstall theme "{{ themeName }}".';
        $message .= ' Remove the following assignments between theme and sales channel assignments: {{ assignments }}.';
        $assignments = '';
        if (\count($themeSalesChannel) > 0) {
            $assignments .= self::formatSalesChannelAssignments($themeSalesChannel, $assignedSalesChannels);
        }

        if (\count($childThemeSalesChannel) > 0) {
            $assignments .= self::formatSalesChannelAssignments($childThemeSalesChannel, $assignedSalesChannels);
        }
        $parameters['assignments'] = $assignments;

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::THEME_ASSIGNMENT,
            $message,
            $parameters,
            $e
        );
    }

    public static function errorLoadingRuntimeConfig(string $themeId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ERROR_LOADING_RUNTIME_CONFIG,
            'Error loading runtime config for theme with id "{{ themeId }}"',
            ['themeId' => $themeId]
        );
    }

    public static function errorLoadingFromPluginRegistry(string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ERROR_LOADING_FROM_PLUGIN_REGISTRY,
            'Error loading theme with technical name "{{ technicalName }}" from plugin registry',
            ['technicalName' => $technicalName]
        );
    }

    public static function themeCreationFailure(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::THEME_CREATION_FAILURE,
            $message,
        );
    }

    /**
     * @param array<string, array<int, string>> $assignmentMapping
     * @param array<string, string> $assignedSalesChannels
     */
    private static function formatSalesChannelAssignments(array $assignmentMapping, array $assignedSalesChannels): string
    {
        $output = [];
        foreach ($assignmentMapping as $themeName => $salesChannelIds) {
            $salesChannelNames = [];
            foreach ($salesChannelIds as $salesChannelId) {
                $salesChannelNames[] = $assignedSalesChannels[$salesChannelId] ?? $salesChannelId;
            }

            $output[] = \sprintf('"%s" => "%s"', $themeName, implode(', ', $salesChannelNames));
        }

        return implode(', ', $output);
    }
}
