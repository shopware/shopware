<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(IntegrationException::class)]
class IntegrationExceptionTest extends TestCase
{
    public function testAdminIntegrationChangeNotAllowed(): void
    {
        $exception = IntegrationException::adminIntegrationChangeNotAllowed();

        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
        static::assertSame(IntegrationException::ADMIN_INTEGRATION_CHANGE_NOT_ALLOWED, $exception->getErrorCode());
        static::assertSame('An admin integration can only be changed or deleted by an admin.', $exception->getMessage());
    }
}
