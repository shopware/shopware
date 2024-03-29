<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\LicenseDomainVerificationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(LicenseDomainVerificationException::class)]
class LicenseDomainVerificationExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__STORE_LICENSE_DOMAIN_VALIDATION_FAILED',
            (new LicenseDomainVerificationException('license.domain'))->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            (new LicenseDomainVerificationException('license.domain'))->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'License host verification failed for domain "license.domain."',
            (new LicenseDomainVerificationException('license.domain'))->getMessage()
        );
    }
}
