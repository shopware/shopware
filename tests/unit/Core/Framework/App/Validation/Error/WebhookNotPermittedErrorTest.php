<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\WebhookNotPermittedError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookNotPermittedError::class)]
class WebhookNotPermittedErrorTest extends TestCase
{
    public function testError(): void
    {
        $error = new WebhookNotPermittedError(['hook1: some.event', 'hook2: other.event']);

        static::assertSame(
            "This app is not permitted to subscribe to the following webhooks:\n- hook1: some.event\n- hook2: other.event",
            $error->getMessage()
        );
        static::assertSame(AppException::VALIDATION_FAILED, $error->getErrorCode());
        static::assertSame([], $error->getParameters());
    }

    public function testARefusedSubscriptionRefusesAnInstall(): void
    {
        $error = new WebhookNotPermittedError(['hook1: some.event']);

        static::assertTrue($error->isBlocking());
    }
}
