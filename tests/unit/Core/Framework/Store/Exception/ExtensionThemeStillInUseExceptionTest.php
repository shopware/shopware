<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\ExtensionThemeStillInUseException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package merchant-services
 *
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Exception\ExtensionThemeStillInUseException
 */
class ExtensionThemeStillInUseExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__EXTENSION_THEME_STILL_IN_USE',
            (new ExtensionThemeStillInUseException('36cf0d7a018a41719f29f50f2a056179'))->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_FORBIDDEN,
            (new ExtensionThemeStillInUseException('36cf0d7a018a41719f29f50f2a056179'))->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'The extension with id "36cf0d7a018a41719f29f50f2a056179"can not be removed because it\'s theme is still assigned to a sales channel.',
            (new ExtensionThemeStillInUseException('36cf0d7a018a41719f29f50f2a056179', ['foo' => 'bar']))->getMessage()
        );
    }
}
