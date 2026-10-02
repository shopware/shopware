<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\NotHookableError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NotHookableError::class)]
class NotHookableErrorTest extends TestCase
{
    public function testError(): void
    {
        $error = new NotHookableError(['hook1: some.event', 'hook2: other.event']);

        static::assertSame(
            "The following webhooks are not hookable:\n- hook1: some.event\n- hook2: other.event",
            $error->getMessage()
        );
        static::assertSame(AppException::VALIDATION_FAILED, $error->getErrorCode());
        static::assertSame([], $error->getParameters());
    }

    public function testAnUnknownEventDoesNotRefuseAnInstall(): void
    {
        $error = new NotHookableError(['hook1: some.event']);

        static::assertFalse($error->isBlocking());
    }
}
