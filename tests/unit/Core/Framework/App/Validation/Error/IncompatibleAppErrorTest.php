<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\IncompatibleAppError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IncompatibleAppError::class)]
class IncompatibleAppErrorTest extends TestCase
{
    public function testError(): void
    {
        $error = new IncompatibleAppError('MyApp');

        static::assertSame('App MyApp is not compatible with this Shopware version', $error->getMessage());
        static::assertSame(AppException::NOT_COMPATIBLE, $error->getErrorCode());
        static::assertSame(['name' => 'MyApp'], $error->getParameters());
        static::assertTrue($error->isBlocking());
    }
}
