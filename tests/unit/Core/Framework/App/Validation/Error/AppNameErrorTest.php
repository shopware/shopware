<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppNameError::class)]
class AppNameErrorTest extends TestCase
{
    public function testError(): void
    {
        $error = new AppNameError('MyApp');

        static::assertSame(
            'The technical app name "MyApp" in the "manifest.xml" and the folder name must be equal.',
            $error->getMessage()
        );
        static::assertSame(AppException::VALIDATION_FAILED, $error->getErrorCode());
        static::assertSame([], $error->getParameters());
        static::assertFalse($error->isBlocking());
    }
}
