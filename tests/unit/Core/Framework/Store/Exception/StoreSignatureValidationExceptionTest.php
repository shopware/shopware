<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\StoreSignatureValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @deprecated tag:v6.7.0 - unused class
 */
#[Package('checkout')]
#[CoversClass(StoreSignatureValidationException::class)]
class StoreSignatureValidationExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);
        static::assertSame(
            'FRAMEWORK__STORE_SIGNATURE_INVALID',
            (new StoreSignatureValidationException('reason'))->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            (new StoreSignatureValidationException('reason'))->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);
        static::assertSame(
            'Store signature validation failed. Error: reason',
            (new StoreSignatureValidationException('reason'))->getMessage()
        );
    }
}
