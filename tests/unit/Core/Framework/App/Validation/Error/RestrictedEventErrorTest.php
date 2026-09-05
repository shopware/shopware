<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\RestrictedEventError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RestrictedEventError::class)]
class RestrictedEventErrorTest extends TestCase
{
    public function testError(): void
    {
        $error = new RestrictedEventError(['licenseHook: commercial_license.provided']);

        static::assertSame(
            "The following webhooks subscribe to events this app is not permitted to receive:\n"
            . '- licenseHook: commercial_license.provided',
            $error->getMessage()
        );
        static::assertSame(AppException::VALIDATION_FAILED, $error->getErrorCode());
        static::assertSame([], $error->getParameters());
    }

    public function testSubscribingToAForbiddenEventRefusesAnInstall(): void
    {
        $error = new RestrictedEventError(['licenseHook: commercial_license.provided']);

        static::assertTrue($error->isBlocking());
    }
}
