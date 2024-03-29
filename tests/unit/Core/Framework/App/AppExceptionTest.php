<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(AppException::class)]
class AppExceptionTest extends TestCase
{
    public function testCannotDeleteManaged(): void
    {
        $e = AppException::cannotDeleteManaged('ManagedApp');

        static::assertEquals(AppException::CANNOT_DELETE_COMPOSER_MANAGED, $e->getErrorCode());
    }

    public function testNotCompatible(): void
    {
        $e = AppException::notCompatible('IncompatibleApp');

        static::assertEquals(AppException::NOT_COMPATIBLE, $e->getErrorCode());
    }

    public function testNotFound(): void
    {
        $e = AppException::notFound('NonExistingApp');

        static::assertInstanceOf(AppNotFoundException::class, $e);
        static::assertEquals(AppException::NOT_FOUND, $e->getErrorCode());
    }

    public function testAlreadyInstalled(): void
    {
        $e = AppException::alreadyInstalled('AlreadyInstalledApp');

        static::assertInstanceOf(AppAlreadyInstalledException::class, $e);
        static::assertEquals(AppException::ALREADY_INSTALLED, $e->getErrorCode());
    }

    public function testRegistrationFailed(): void
    {
        $e = AppException::registrationFailed('ToBeRegisteredApp', 'Invalid signature');

        static::assertEquals(AppException::REGISTRATION_FAILED, $e->getErrorCode());
        static::assertEquals('App registration for "ToBeRegisteredApp" failed: Invalid signature', $e->getMessage());
    }

    public function testLicenseCouldNotBeVerified(): void
    {
        $e = AppException::licenseCouldNotBeVerified('UnlicensedApp');

        static::assertEquals(AppException::LICENSE_COULD_NOT_BE_VERIFIED, $e->getErrorCode());
    }

    public function testInvalidConfiguration(): void
    {
        $e = AppException::invalidConfiguration('InvalidlyConfiguredApp', new AppNameError('InvalidlyConfiguredApp'));

        static::assertEquals(AppException::INVALID_CONFIGURATION, $e->getErrorCode());
    }

    public function testInstallationFailed(): void
    {
        $e = AppException::installationFailed('AnyAppName', 'reason');

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertEquals(AppException::INSTALLATION_FAILED, $e->getErrorCode());
        static::assertEquals('App installation for "AnyAppName" failed: reason', $e->getMessage());
    }

    public function testAppSecretRequiredForFeatures(): void
    {
        $e = AppException::appSecretRequiredForFeatures('MyApp', ['Modules']);

        static::assertEquals(AppException::FEATURES_REQUIRE_APP_SECRET, $e->getErrorCode());
        static::assertEquals('App "MyApp" could not be installed/updated because it uses features Modules but has no secret', $e->getMessage());

        $e = AppException::appSecretRequiredForFeatures('MyApp', ['Modules', 'Payments', 'Webhooks']);

        static::assertEquals(AppException::FEATURES_REQUIRE_APP_SECRET, $e->getErrorCode());
        static::assertEquals('App "MyApp" could not be installed/updated because it uses features Modules, Payments and Webhooks but has no secret', $e->getMessage());
    }
}
