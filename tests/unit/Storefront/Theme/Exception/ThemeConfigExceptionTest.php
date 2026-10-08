<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\ThemeConfigException;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeConfigException::class)]
class ThemeConfigExceptionTest extends TestCase
{
    #[TestDox('tryToThrow is a no-op while no error was added')]
    public function testTryToThrowWithoutErrors(): void
    {
        $exception = new ThemeConfigException();

        $exception->tryToThrow();

        static::assertSame('THEME_CONFIG_EXCEPTION', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertStringContainsString('There are 0 error(s) while validating the theme config.', $exception->getMessage());
    }

    #[TestDox('added errors update the message and make tryToThrow throw')]
    public function testAddUpdatesMessageAndThrows(): void
    {
        $exception = new ThemeConfigException();
        $exception->add(ThemeException::invalidThemeBundle('MyTheme'));

        static::assertStringContainsString('There are 1 error(s) while validating the theme config.', $exception->getMessage());

        $this->expectExceptionObject($exception);
        $exception->tryToThrow();
    }

    #[TestDox('getErrors flattens shopware and generic inner exceptions')]
    public function testGetErrors(): void
    {
        $exception = new ThemeConfigException();
        $exception->add(ThemeException::invalidThemeBundle('MyTheme'));
        $exception->add(new \RuntimeException('broken config'));

        $errors = iterator_to_array($exception->getErrors(), false);

        static::assertCount(2, $errors);
        static::assertSame('Unable to find the theme.json for "MyTheme"', $errors[0]['detail']);
        static::assertSame('broken config', $errors[1]['detail']);
    }
}
