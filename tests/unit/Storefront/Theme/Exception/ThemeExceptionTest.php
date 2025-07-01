<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeException::class)]
class ThemeExceptionTest extends TestCase
{
    public function testThemeMediaStillInUse(): void
    {
        $exception = ThemeException::themeMediaStillInUse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ThemeException::THEME_MEDIA_IN_USE_EXCEPTION, $exception->getErrorCode());
        static::assertSame('Media entity is still in use by a theme', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testSalesChannelNotFound(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $exception = ThemeException::salesChannelNotFound($salesChannelId);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ThemeException::THEME_SALES_CHANNEL_NOT_FOUND, $exception->getErrorCode());
        static::assertStringContainsString($salesChannelId, $exception->getMessage());
        static::assertSame(['entity' => 'sales channel', 'field' => 'id', 'value' => $salesChannelId], $exception->getParameters());
    }

    public function testCouldNotFindThemeByName(): void
    {
        $themeName = 'test-theme';
        $exception = ThemeException::couldNotFindThemeByName($themeName);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ThemeException::INVALID_THEME_BY_NAME, $exception->getErrorCode());
        static::assertStringContainsString($themeName, $exception->getMessage());
        static::assertSame(['entity' => 'theme', 'field' => 'name', 'value' => $themeName], $exception->getParameters());
    }

    public function testCouldNotFindThemeById(): void
    {
        $themeId = 'test-theme-id';
        $exception = ThemeException::couldNotFindThemeById($themeId);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ThemeException::INVALID_THEME_BY_ID, $exception->getErrorCode());
        static::assertStringContainsString($themeId, $exception->getMessage());
        static::assertSame(['entity' => 'theme', 'field' => 'id', 'value' => $themeId], $exception->getParameters());
    }

    public function testInvalidScssValue(): void
    {
        $value = 'invalid-value';
        $type = 'color';
        $name = 'primary-color';
        $exception = ThemeException::InvalidScssValue($value, $type, $name);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ThemeException::INVALID_SCSS_VAR, $exception->getErrorCode());
        static::assertSame('SCSS Value "invalid-value" is not valid for type "color".', $exception->getMessage());
        static::assertSame(['name' => $name, 'value' => $value, 'type' => $type], $exception->getParameters());
    }

    public function testThemeCompileException(): void
    {
        $themeName = 'test-theme';
        $message = 'compile error';
        $exception = ThemeException::themeCompileException($themeName, $message);

        static::assertSame('THEME__COMPILING_ERROR', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('Unable to compile the theme "test-theme". compile error', $exception->getMessage());
    }

    public function testErrorLoadingRuntimeConfig(): void
    {
        $themeId = 'test-theme-id';
        $exception = ThemeException::errorLoadingRuntimeConfig($themeId);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(ThemeException::ERROR_LOADING_RUNTIME_CONFIG, $exception->getErrorCode());
        static::assertSame('Error loading runtime config for theme with id "test-theme-id"', $exception->getMessage());
        static::assertSame(['themeId' => $themeId], $exception->getParameters());
    }

    public function testErrorLoadingFromPluginRegistry(): void
    {
        $technicalName = 'test-technical-name';
        $exception = ThemeException::errorLoadingFromPluginRegistry($technicalName);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(ThemeException::ERROR_LOADING_FROM_PLUGIN_REGISTRY, $exception->getErrorCode());
        static::assertSame('Error loading theme with technical name "test-technical-name" from plugin registry', $exception->getMessage());
        static::assertSame(['technicalName' => $technicalName], $exception->getParameters());
    }
}
