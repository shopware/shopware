<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\ThemeConfigException;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeConfigException::class)]
class ThemeConfigExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $exception = new ThemeConfigException();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('THEME_CONFIG_EXCEPTION', $exception->getErrorCode());
        static::assertSame('There are 0 error(s) while validating the theme config.', $exception->getMessage());
        static::assertEmpty($exception->getExceptions());
    }

    public function testAddException(): void
    {
        $exception = new ThemeConfigException();
        $innerException = new \RuntimeException('Test error');

        $result = $exception->add($innerException);

        static::assertSame($exception, $result);
        static::assertCount(1, $exception->getExceptions());
        static::assertSame($innerException, $exception->getExceptions()[0]);
        static::assertSame('There are 1 error(s) while validating the theme config.', $exception->getMessage());
    }

    public function testAddMultipleExceptions(): void
    {
        $exception = new ThemeConfigException();
        $innerException1 = new \RuntimeException('Test error 1');
        $innerException2 = new \InvalidArgumentException('Test error 2');

        $exception->add($innerException1);
        $exception->add($innerException2);

        static::assertCount(2, $exception->getExceptions());
        static::assertSame($innerException1, $exception->getExceptions()[0]);
        static::assertSame($innerException2, $exception->getExceptions()[1]);
        static::assertSame('There are 2 error(s) while validating the theme config.', $exception->getMessage());
    }

    public function testTryToThrowWithNoExceptions(): void
    {
        $exception = new ThemeConfigException();

        // Should not throw when no exceptions are added
        $exception->tryToThrow();

        static::assertCount(0, $exception->getExceptions());
    }

    public function testTryToThrowWithExceptions(): void
    {
        $exception = new ThemeConfigException();
        $innerException = new \RuntimeException('Test error');
        $exception->add($innerException);

        $this->expectException(ThemeConfigException::class);
        $this->expectExceptionMessage('There are 1 error(s) while validating the theme config.');

        $exception->tryToThrow();
    }

    public function testGetErrorsWithNoExceptions(): void
    {
        $exception = new ThemeConfigException();

        $errors = [];
        foreach ($exception->getErrors(true) as $error) {
            $errors[] = $error;
        }

        static::assertEmpty($errors);
    }

    public function testGetErrorsWithTrace(): void
    {
        $exception = new ThemeConfigException();
        $innerException = new \RuntimeException('Test error');
        $exception->add($innerException);

        $errors = [];
        foreach ($exception->getErrors(true) as $error) {
            $errors[] = $error;
        }

        static::assertCount(1, $errors);
        static::assertArrayHasKey('meta', $errors[0]);
        static::assertArrayHasKey('trace', $errors[0]['meta']);
        static::assertIsArray($errors[0]['meta']['trace']);
    }

    public function testGetErrorsWithMultipleExceptions(): void
    {
        $exception = new ThemeConfigException();
        $innerException1 = ThemeException::InvalidScssValue('#invalid-color', 'color', 'sw-color-brand-primary');
        $innerException2 = ThemeException::InvalidScssValue('#invalid-color', 'color', 'sw-color-brand-secondary');
        $exception->add($innerException1);
        $exception->add($innerException2);

        $errors = [];
        foreach ($exception->getErrors() as $error) {
            $errors[] = $error;
        }

        static::assertCount(2, $errors);
        static::assertSame('400', $errors[0]['status']);
        static::assertSame('400', $errors[1]['status']);
    }

    public function testGetExceptions(): void
    {
        $exception = new ThemeConfigException();
        $innerException1 = new \RuntimeException('Test error 1');
        $innerException2 = new \InvalidArgumentException('Test error 2');

        $exception->add($innerException1);
        $exception->add($innerException2);

        $exceptions = $exception->getExceptions();

        static::assertCount(2, $exceptions);
        static::assertSame($innerException1, $exceptions[0]);
        static::assertSame($innerException2, $exceptions[1]);
    }

    public function testMessageUpdateAfterAddingExceptions(): void
    {
        $exception = new ThemeConfigException();
        static::assertSame('There are 0 error(s) while validating the theme config.', $exception->getMessage());

        $exception->add(new \RuntimeException('Error 1'));
        static::assertSame('There are 1 error(s) while validating the theme config.', $exception->getMessage());

        $exception->add(new \RuntimeException('Error 2'));
        static::assertSame('There are 2 error(s) while validating the theme config.', $exception->getMessage());
    }

    public function testChainingAddMethod(): void
    {
        $exception = new ThemeConfigException();
        $innerException1 = new \RuntimeException('Test error 1');
        $innerException2 = new \RuntimeException('Test error 2');

        $result = $exception
            ->add($innerException1)
            ->add($innerException2);

        static::assertSame($exception, $result);
        static::assertCount(2, $exception->getExceptions());
    }
}
