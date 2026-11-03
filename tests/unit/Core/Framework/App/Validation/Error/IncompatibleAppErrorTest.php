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
    public function testDescribesTheIncompatibleApp(): void
    {
        $error = new IncompatibleAppError('SwagExample');

        static::assertSame('App SwagExample is not compatible with this Shopware version', $error->getMessage());
        static::assertSame(AppException::NOT_COMPATIBLE, $error->getErrorCode());
        static::assertSame(['name' => 'SwagExample'], $error->getParameters());
        static::assertTrue($error->isBlocking());
    }
}
