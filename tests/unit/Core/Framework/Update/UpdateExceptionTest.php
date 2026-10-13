<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\UpdateException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdateException::class)]
class UpdateExceptionTest extends TestCase
{
    public function testAutoUpdateDisabled(): void
    {
        $exception = UpdateException::autoUpdateDisabled();

        static::assertSame('Auto update is disabled', $exception->getMessage());
        static::assertSame('FRAMEWORK__AUTO_UPDATE_DISABLED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
    }

    public function testUpdateModuleHidden(): void
    {
        $exception = UpdateException::updateModuleHidden();

        static::assertSame('The update module is hidden', $exception->getMessage());
        static::assertSame('FRAMEWORK__UPDATE_MODULE_HIDDEN', $exception->getErrorCode());
        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
    }

    public function testClusterSetupNotSupported(): void
    {
        $exception = UpdateException::clusterSetupNotSupported();

        static::assertSame('Updating through the Administration is not possible on cluster setups', $exception->getMessage());
        static::assertSame('FRAMEWORK__UPDATE_CLUSTER_SETUP_NOT_SUPPORTED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
    }

    public function testMissingUser(): void
    {
        $exception = UpdateException::missingUser();

        static::assertSame('The current request does not contain a valid user', $exception->getMessage());
        static::assertSame('FRAMEWORK__UPDATE_MISSING_USER', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testMissingVersion(): void
    {
        $exception = UpdateException::missingVersion();

        static::assertSame('The request is missing a valid version parameter', $exception->getMessage());
        static::assertSame('FRAMEWORK__UPDATE_MISSING_VERSION', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }
}
