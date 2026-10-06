<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MissingPermissionError::class)]
class MissingPermissionErrorTest extends TestCase
{
    public function testError(): void
    {
        $error = new MissingPermissionError(['product:read', 'order:read']);

        static::assertSame(
            "The following permissions are missing:\n- product:read\n- order:read",
            $error->getMessage()
        );
        static::assertSame(AppException::VALIDATION_FAILED, $error->getErrorCode());
        static::assertSame([], $error->getParameters());
        static::assertTrue($error->isBlocking());
    }
}
